@extends('layouts.app')

@section('title', 'Schema editor')
@section('section', 'System Admin')

@section('content')
    <div class="page-heading">
        <span class="eyebrow">SYSTEM ADMINISTRATION</span>
        <h1>Schema editor</h1>
        <p>Manage optional custom fields on application tables.</p>
    </div>

    <div class="inline-notice section-gap">
        <strong>Protected schema</strong>
        <p>Existing application fields, account and audit tables, and framework tables cannot be changed here. New fields are nullable and do not automatically appear in application forms. Only empty custom fields created here can be deleted.</p>
    </div>

    <section class="panel section-gap">
        <div class="panel-heading">
            <div>
                <h2>Add a custom field</h2>
                <p>Field names must start with <code>custom_</code>. New fields start empty.</p>
            </div>
        </div>
        <form method="post" action="{{ route('schema-editor.store') }}" class="form-grid">
            @csrf
            <label class="field">
                <span>Application table <b>*</b></span>
                <select name="table_name" required>
                    <option value="">Choose a table</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table['name'] }}" @selected(old('table_name') === $table['name'])>{{ $table['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Field name <b>*</b></span>
                <input name="column_name" value="{{ old('column_name') }}" maxlength="64" pattern="custom_[a-z][a-z0-9_]*" placeholder="custom_notes" required>
            </label>
            <label class="field">
                <span>Field type <b>*</b></span>
                <select name="column_type" required>
                    @foreach ($columnTypes as $type => $label)
                        <option value="{{ $type }}" @selected(old('column_type', 'string') === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="form-actions"><button class="button button-primary" type="submit">Add field</button></div>
        </form>
    </section>

    <div class="section-gap">
        @foreach ($tables as $table)
            <section class="panel section-gap">
                <div class="panel-heading">
                    <div>
                        <h2>{{ $table['name'] }}</h2>
                        <p>{{ count($table['columns']) }} existing fields · {{ $table['managedFields']->count() }} editor-managed custom fields</p>
                    </div>
                </div>
                <details>
                    <summary>View existing fields</summary>
                    <div class="detail-grid section-gap">
                        @foreach ($table['columns'] as $column)
                            <div>
                                <span class="detail-label">{{ $column['name'] }}</span>
                                <strong>{{ $column['type'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </details>

                @if ($table['managedFields']->isNotEmpty())
                    <div class="section-gap">
                        @foreach ($table['managedFields'] as $field)
                            <div class="inline-notice section-gap">
                                <strong>{{ $field->column_name }}</strong>
                                <p>{{ $columnTypes[$field->column_type] ?? $field->column_type }}</p>
                                <form method="post" action="{{ route('schema-editor.update', $field) }}" class="form-grid">
                                    @csrf
                                    @method('put')
                                    <label class="field">
                                        <span>Rename field</span>
                                        <input name="column_name" value="{{ old('column_name', $field->column_name) }}" maxlength="64" pattern="custom_[a-z][a-z0-9_]*" required>
                                    </label>
                                    <div class="form-actions"><button class="button button-secondary" type="submit">Save name</button></div>
                                </form>
                                <form method="post" action="{{ route('schema-editor.destroy', $field) }}" class="form-grid section-gap">
                                    @csrf
                                    @method('delete')
                                    <label class="field">
                                        <span>Type {{ $field->column_name }} to confirm deletion</span>
                                        <input name="confirmation" autocomplete="off" required>
                                    </label>
                                    <div class="form-actions"><button class="button button-danger" type="submit">Delete empty field</button></div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endsection
