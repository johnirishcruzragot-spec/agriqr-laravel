@extends('layouts.app')

@section('title', $managedUser->exists ? 'Edit team member' : 'Add team member')
@section('section', 'Team access')

@section('content')
    <div class="page-heading">
        <a class="back-link" href="{{ route('users.index') }}">← Back to team access</a>
        <span class="eyebrow">ACCESS MANAGEMENT</span>
        <h1>{{ $managedUser->exists ? 'Edit team member' : 'Add team member' }}</h1>
        <p>Choose the minimum role this person needs to do their work.</p>
    </div>
    <form method="post" action="{{ $managedUser->exists ? route('users.update', $managedUser) : route('users.store') }}" class="form-layout">
        @csrf
        @if ($managedUser->exists) @method('put') @endif
        <section class="panel form-panel">
            <div class="form-grid">
                <label class="field"><span>Full name <b>*</b></span><input name="name" value="{{ old('name', $managedUser->name) }}" maxlength="255" required></label>
                <label class="field"><span>Username <b>*</b></span><input name="username" value="{{ old('username', $managedUser->username) }}" maxlength="50" required></label>
                <label class="field"><span>Email address <b>*</b></span><input type="email" name="email" value="{{ old('email', $managedUser->email) }}" maxlength="254" required></label>
                <label class="field"><span>Role <b>*</b></span><select name="role" required>@foreach ($roles as $role)<option value="{{ $role }}" @selected(old('role', $managedUser->role ?: 'staff') === $role)>{{ str($role)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                <label class="field"><span>{{ $managedUser->exists ? 'New password' : 'Password' }} @unless ($managedUser->exists)<b>*</b>@endunless</span><input type="password" name="password" minlength="12" autocomplete="new-password" @required(! $managedUser->exists)><small>{{ $managedUser->exists ? 'Leave blank to keep the current password.' : 'Use at least 12 characters.' }}</small></label>
                <label class="field"><span>Confirm password</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label>
                <label class="field"><span>Account status</span><select name="is_active"><option value="1" @selected((string) old('is_active', $managedUser->exists ? (int) $managedUser->is_active : 1) === '1')>Active</option><option value="0" @selected((string) old('is_active', $managedUser->exists ? (int) $managedUser->is_active : 1) === '0')>Inactive</option></select></label>
            </div>
        </section>
        <div class="form-actions"><a class="button button-secondary" href="{{ route('users.index') }}">Cancel</a><button class="button button-primary" type="submit">{{ $managedUser->exists ? 'Save changes' : 'Create account' }}</button></div>
    </form>
@endsection
