@extends('layouts.app')

@section('title', 'Team access')
@section('section', 'Team access')

@section('content')
    <div class="page-heading heading-with-action">
        <div><span class="eyebrow">ACCESS MANAGEMENT</span><h1>Team access</h1><p>Manage staff accounts and their AgriQR workspace permissions.</p></div>
        <a class="button button-primary" href="{{ route('users.create') }}"><span>＋</span> Add team member</a>
    </div>
    <section class="panel">
        <div class="table-wrap"><table><thead><tr><th>TEAM MEMBER</th><th>USERNAME</th><th>ROLE</th><th>STATUS</th><th></th></tr></thead><tbody>
            @foreach ($users as $user)
                <tr><td><div class="person-cell"><span class="person-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span><div><strong>{{ $user->name }}</strong><small class="table-sub">{{ $user->email }}</small></div></div></td><td>{{ $user->username ?: '—' }}</td><td><span class="role-label">{{ str($user->role)->replace('_', ' ')->title() }}</span></td><td><span class="status {{ $user->is_active ? 'status-green' : 'status-neutral' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td><td>@if ($user->role !== 'super_admin' || auth()->user()->role === 'super_admin')<a class="table-action" href="{{ route('users.edit', $user) }}">Edit <span>→</span></a>@else<span class="table-count">Protected</span>@endif</td></tr>
            @endforeach
        </tbody></table></div>
        <div class="pagination-wrap">{{ $users->links() }}</div>
    </section>
@endsection
