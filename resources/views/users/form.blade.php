@extends('layouts.app')

@section('title', $managedUser->exists ? 'Edit team member' : 'Add team member')
@section('section', 'Team access')

@section('content')
    @php
        $selectedRole = old('role', $managedUser->role ?: 'technician');
        $selectedModuleAccess = old(
            'module_access',
            $managedUser->module_access ?? \App\Models\User::modulesForRole($selectedRole),
        );
    @endphp
    <div class="page-heading">
        @if (auth()->user()->canUseButton('users.list'))<a class="back-link" href="{{ route('users.index') }}">← Back to team access</a>@endif
        <span class="eyebrow">ACCESS MANAGEMENT</span>
        <h1>{{ $managedUser->exists ? 'Edit team member' : 'Add team member' }}</h1>
        <p>Choose a role and, for Technicians, the modules this account can access.</p>
    </div>
    <form method="post" action="{{ $managedUser->exists ? route('users.update', $managedUser) : route('users.store') }}" class="form-layout" data-user-security-form data-original-role="{{ $managedUser->role }}" data-original-modules="{{ implode(',', $managedUser->module_access ?? \App\Models\User::modulesForRole($managedUser->role ?: 'technician')) }}">
        @csrf
        @if ($managedUser->exists) @method('put') @endif
        <section class="panel form-panel">
            <div class="form-grid">
                <label class="field"><span>Full name <b>*</b></span><input name="name" value="{{ old('name', $managedUser->name) }}" maxlength="255" required></label>
                <label class="field"><span>Username <b>*</b></span><input name="username" value="{{ old('username', $managedUser->username) }}" maxlength="50" required></label>
                <label class="field"><span>Email address <b>*</b></span><input type="email" name="email" value="{{ old('email', $managedUser->email) }}" maxlength="254" required></label>
                <label class="field"><span>Role <b>*</b></span><select name="role" required>@foreach ($roles as $role)<option value="{{ $role }}" @selected(old('role', $managedUser->role ?: 'technician') === $role)>{{ str($role)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                <label class="field"><span>{{ $managedUser->exists ? 'New password' : 'Password' }} @unless ($managedUser->exists)<b>*</b>@endunless</span><input type="password" name="password" minlength="12" autocomplete="new-password" @required(! $managedUser->exists)><small>{{ $managedUser->exists ? 'Leave blank to keep the current password.' : 'Use at least 12 characters.' }}</small></label>
                <label class="field"><span>Confirm password</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label>
                <label class="field"><span>Account status</span><select name="is_active"><option value="1" @selected((string) old('is_active', $managedUser->exists ? (int) $managedUser->is_active : 1) === '1')>Active</option><option value="0" @selected((string) old('is_active', $managedUser->exists ? (int) $managedUser->is_active : 1) === '0')>Inactive</option></select></label>
            </div>
        </section>
        @if ($canManageModuleAccess)
            <section class="panel form-panel">
                <div class="form-section-heading">
                    <div><h2>Module access</h2><p>Select what this individual account can see and open. Its role remains the maximum access it can receive.</p></div>
                </div>
                <div class="form-grid">
                    @foreach ($moduleLabels as $module => $label)
                        @php
                            $moduleRoles = collect($roleModules)
                                ->filter(fn (array $modules): bool => in_array($module, $modules, true))
                                ->keys()
                                ->all();
                        @endphp
                        <label class="field module-access-option" data-module-access-option data-roles="{{ implode(',', $moduleRoles) }}">
                            <span><input type="checkbox" name="module_access[]" value="{{ $module }}" @checked(in_array($module, (array) $selectedModuleAccess, true)) @disabled(! in_array($selectedRole, $moduleRoles, true))> {{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="form-note">Administrator roles retain full application access. Technician access is limited to the selected modules.</p>
            </section>
        @endif
        <div class="form-actions">@if (auth()->user()->canUseButton('users.list'))<a class="button button-secondary" href="{{ route('users.index') }}">Cancel</a>@endif @if (auth()->user()->canUseButton($managedUser->exists ? 'users.edit' : 'users.create'))<button class="button button-primary" type="submit">{{ $managedUser->exists ? 'Save changes' : 'Create account' }}</button>@endif</div>
    </form>
@endsection
