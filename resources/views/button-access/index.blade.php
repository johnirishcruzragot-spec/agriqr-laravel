@extends('layouts.app')
@section('title', 'Button and action access')
@section('section', 'System Admin')
@section('content')
<div class="page-heading"><span class="eyebrow">SYSTEM ADMINISTRATION</span><h1>Button and action access</h1><p>Set default permissions by role, then customize the buttons and actions available to each account.</p></div>
@if (session('success'))<div class="inline-notice section-gap"><strong>Saved successfully</strong><p>{{ session('success') }}</p></div>@endif
<div class="inline-notice section-gap"><strong>Individual permissions take priority</strong><p>System Admin always has full access. For Admin and Technician accounts, individual settings override role defaults. Dashboard and sign-out remain available so users are not locked out. Module access still applies, and restricted routes are protected on the server.</p></div>
<section class="panel form-panel section-gap">
 <div class="form-section-heading"><span class="section-number">01</span><div><h2>Individual user permissions</h2><p>Select one account to configure its buttons independently from other users with the same role.</p></div></div>
 <form method="get" action="{{ route('button-access.index') }}" class="form-grid"><label class="field"><span>Select user account</span><select name="user_id" required><option value="">Choose an Admin or Technician account</option>@foreach ($users as $account)<option value="{{ $account->id }}" @selected($selectedUser?->id === $account->id)>{{ $account->name }} — {{ ucfirst(str_replace('_', ' ', $account->role)) }} ({{ $account->username ?: $account->email }})</option>@endforeach</select></label><div class="form-actions"><button class="button button-secondary" type="submit">Load account</button></div></form>
 @if ($users->isEmpty())<p class="empty-state">No Admin or Technician accounts are available to configure.</p>@endif
 @if ($selectedUser)
  <div class="detail-note section-gap"><span class="detail-label">CURRENTLY SELECTED ACCOUNT</span><p><strong>{{ $selectedUser->name }}</strong> · {{ ucfirst(str_replace('_', ' ', $selectedUser->role)) }} · {{ $selectedUser->email }}</p>@if ($hasUserOverrides)<span class="status status-amber">Individual permissions configured</span>@else<span class="status status-green">Using role defaults</span>@endif</div>
  <form method="post" action="{{ route('button-access.users.update', $selectedUser) }}" class="form-layout section-gap">@csrf @method('put')
   <div class="inline-notice"><strong>Configure this account</strong><p>Checked actions are enabled. Unchecked actions are blocked for this account. Saving creates an individual override for every available action for this user's role.</p></div>
   @foreach ($actions as $section => $sectionActions)
    @if ($section !== 'Workspace')
    @php($availableActions = $sectionActions->filter(fn (array $action): bool => array_key_exists($selectedUser->role, $action['defaults'])))
    @if ($availableActions->isNotEmpty())<section class="panel form-panel"><div class="form-section-heading"><span class="section-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ $section }}</h2><p>Enable only the actions this account is allowed to perform.</p></div></div><div class="table-wrap"><table><thead><tr><th>BUTTON / ACTION</th><th>ACCOUNT ACCESS</th></tr></thead><tbody>
     @foreach ($availableActions as $key => $action)<tr><td><strong>{{ $action['label'] }}</strong>@if ($action['routes'] !== [] || ($action['server_protected'] ?? false))<small class="table-sub">Also protects the matching page and action.</small>@else<small class="table-sub">Controls this page’s interface only.</small>@endif</td><td><label class="check-line"><input type="checkbox" name="user_buttons[]" value="{{ $key }}" @checked(old('user_buttons') === null ? ($userValues[$key] ?? false) : in_array($key, (array) old('user_buttons', []), true))> Enabled</label></td></tr>@endforeach
    </tbody></table></div></section>@endif
    @endif
   @endforeach
   <div class="form-actions"><button class="button button-primary" type="submit">Save permissions for {{ $selectedUser->name }}</button></div>
  </form>
  <form method="post" action="{{ route('button-access.users.reset', $selectedUser) }}" class="form-actions section-gap" onsubmit="return confirm('Reset this account to its role default permissions?')">@csrf @method('delete')<button class="button button-secondary" type="submit">Reset to {{ ucfirst(str_replace('_', ' ', $selectedUser->role)) }} defaults</button></form>
 @endif
</section>
<section class="panel form-panel section-gap"><div class="form-section-heading"><span class="section-number">02</span><div><h2>Default permissions by role</h2><p>These settings apply to accounts that do not have individual permission overrides.</p></div></div>
 <form method="post" action="{{ route('button-access.update') }}" class="form-layout">@csrf @method('put')<input type="hidden" name="button_access_submitted" value="1">
 @foreach ($actions as $section => $sectionActions)<section class="panel form-panel"><div class="form-section-heading"><span class="section-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h2>{{ $section }}</h2><p>Choose the default access for each role.</p></div></div><div class="table-wrap"><table><thead><tr><th>BUTTON / ACTION</th>@foreach ($roles as $role => $label)<th>{{ strtoupper($label) }}</th>@endforeach</tr></thead><tbody>
  @foreach ($sectionActions as $key => $action)<tr><td><strong>{{ $action['label'] }}</strong>@if ($action['routes'] !== [] || ($action['server_protected'] ?? false))<small class="table-sub">Also protects the matching page and action.</small>@else<small class="table-sub">Controls this page’s interface only.</small>@endif</td>@foreach ($roles as $role => $label)<td>@if (array_key_exists($role, $action['defaults']))<label class="check-line"><input type="checkbox" name="buttons[{{ $role }}][]" value="{{ $key }}" @checked(old('button_access_submitted') === null ? $values[$role][$key] : in_array($key, (array) old("buttons.{$role}", []), true))> Enabled</label>@else<span class="status status-neutral">Not available</span>@endif</td>@endforeach</tr>@endforeach
 </tbody></table></div></section>@endforeach
 <div class="form-actions"><a class="button button-secondary" href="{{ route('schema-editor.index') }}">Cancel</a><button class="button button-primary" type="submit">Save role defaults</button></div>
 </form>
</section>
@endsection
