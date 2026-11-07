@extends('layouts.app')

@section('title', 'Team access')
@section('section', 'Team access')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">ACCESS MANAGEMENT</span><h1>Team access</h1><p>Manage Admin and Technician accounts and their AgriQR workspace permissions.</p></div>
        @if (auth()->user()->canUseButton('users.create'))<a class="button button-primary" href="{{ route('users.create') }}"><span>＋</span> Add team member</a>@endif
    </div>
    <section class="panel">
        <div class="table-wrap"><table><thead><tr><th>TEAM MEMBER</th><th>USERNAME</th><th>ROLE</th><th>MODULE ACCESS</th><th>STATUS</th><th></th></tr></thead><tbody>
            @foreach ($users as $user)
                @php
                    $moduleAccessLabels = array_map(
                        fn (string $module): string => \App\Models\User::moduleLabels()[$module],
                        $user->accessibleModules(),
                    );
                @endphp
                <tr>
                    <td><div class="person-cell"><span class="person-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><strong>{{ $user->name }}</strong><small class="table-sub">{{ $user->email }}</small></div></div></td>
                    <td>{{ $user->username ?: '—' }}</td>
                    <td><span class="role-label">{{ str($user->role)->replace('_', ' ')->title() }}</span></td>
                    <td>{{ $user->role === 'admin' ? 'All modules' : (implode(', ', $moduleAccessLabels) ?: 'No modules') }}</td>
                    <td><span class="status {{ $user->is_active ? 'status-green' : 'status-neutral' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>@if (auth()->user()->canUseButton('users.edit') && ($user->role !== 'system_admin' || auth()->user()->role === 'system_admin'))<a class="table-action" href="{{ route('users.edit', $user) }}">Edit <span>→</span></a>@endif</td>
                </tr>
            @endforeach
        </tbody></table></div>
        <div class="pagination-wrap">{{ $users->links() }}</div>
    </section>
@endsection
