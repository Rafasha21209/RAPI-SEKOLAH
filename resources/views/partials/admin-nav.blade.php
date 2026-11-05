{{--
    Tombol navigasi modul admin yang rapi & seragam.
    Pemakaian: @include('partials.admin-nav', ['active' => 'users'])
    Nilai active: events | rooms | roles | users | reports
--}}
@php $active = $active ?? ''; @endphp
<nav class="admin-nav" aria-label="Menu admin">
    <a href="{{ route('events.index') }}" class="{{ $active === 'events' ? 'is-active' : '' }}">Agenda</a>
    <a href="{{ route('rooms.index') }}"  class="{{ $active === 'rooms'  ? 'is-active' : '' }}">Ruangan</a>
    <a href="{{ route('roles.index') }}"  class="{{ $active === 'roles'  ? 'is-active' : '' }}">Role</a>
    <a href="{{ route('users.index') }}"  class="{{ $active === 'users'  ? 'is-active' : '' }}">User &amp; Assign Role</a>
    <a href="{{ route('reports.index') }}" class="{{ $active === 'reports' ? 'is-active' : '' }}">Laporan</a>
</nav>
