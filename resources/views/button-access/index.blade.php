@extends('layouts.app')

@section('title', 'Button and action access')
@section('section', 'System Admin')

@section('content')
    <div class="page-heading">
        <span class="eyebrow">SYSTEM ADMINISTRATION</span>
        <h1>Button and action access</h1>
        <p>Choose which application actions Admin and Technician accounts can see and use.</p>
    </div>

    <div class="inline-notice section-gap">
        <strong>Visibility and protection are linked</strong>
        <p>Turning an action off hides its controls and blocks its associated application routes for that role. Existing module access still applies. System Admin access and its sign-out remain outside these settings so the configuration can always be restored.</p>
    </div>

    <form method="post" action="{{ route('button-access.update') }}" class="form-layout section-gap">
        @csrf
        @method('put')
        <input type="hidden" name="button_access_submitted" value="1">

        @foreach ($actions as $section => $sectionActions)
            <section class="panel form-panel">
                <div class="form-section-heading">
                    <span class="section-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <div><h2>{{ $section }}</h2><p>Choose which roles can see and use each action.</p></div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr><th>BUTTON / ACTION</th>@foreach ($roles as $role => $label)<th>{{ strtoupper($label) }}</th>@endforeach</tr>
                        </thead>
                        <tbody>
                            @foreach ($sectionActions as $key => $action)
                                <tr>
                                    <td>
                                        <strong>{{ $action['label'] }}</strong>
                                        @if ($action['routes'] !== [] || ($action['server_protected'] ?? false))
                                            <small class="table-sub">Also protects the matching page and action.</small>
                                        @else
                                            <small class="table-sub">Controls this page’s interface only.</small>
                                        @endif
                                    </td>
                                    @foreach ($roles as $role => $label)
                                        <td>
                                            @if (array_key_exists($role, $action['defaults']))
                                                <label class="check-line">
                                                    <input
                                                        type="checkbox"
                                                        name="buttons[{{ $role }}][]"
                                                        value="{{ $key }}"
                                                        @checked(old('button_access_submitted') === null
                                                            ? $values[$role][$key]
                                                            : in_array($key, (array) old("buttons.{$role}", []), true))
                                                    >
                                                    Enabled
                                                </label>
                                            @else
                                                <span class="status status-neutral">Not available</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <div class="form-actions">
            <a class="button button-secondary" href="{{ route('schema-editor.index') }}">Cancel</a>
            <button class="button button-primary" type="submit">Save button access</button>
        </div>
    </form>
@endsection
