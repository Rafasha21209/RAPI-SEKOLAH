@php
    $authUser  = Auth::user();
    $roleName  = $authUser?->role?->role_name;
    $isAdmin   = $roleName === 'Waka/Admin';
    $displayName = $authUser ? ($authUser->fullname ?: $authUser->username) : null;
    $initial   = $displayName ? mb_strtoupper(mb_substr($displayName, 0, 1)) : '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAPI-Sekolah</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <div class="app-shell">

        {{-- ============================== SIDEBAR ============================== --}}
        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <a href="{{ route('calendar') }}" class="sidebar-brand">
                <span class="sidebar-logo" aria-hidden="true">R</span>
                <span class="sidebar-brand-text">
                    <strong>RAPI-Sekolah</strong>
                    <small>Agenda &amp; Fasilitas</small>
                </span>
            </a>

            <nav class="sidebar-nav">
                <span class="sidebar-label">Menu</span>
                <ul>
                    <li>
                        <a href="{{ route('calendar') }}"
                           class="sidebar-link {{ request()->routeIs('calendar', 'public.calendar') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Calendar</span>
                        </a>
                    </li>

                    @auth
                        <li>
                            <a href="{{ route('dashboard') }}"
                               class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('events.index') }}"
                               class="sidebar-link {{ request()->routeIs('events.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                                <span>Agenda &amp; Ruangan</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('complaints.index') }}"
                               class="sidebar-link {{ request()->routeIs('complaints.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <span>Pengaduan</span>
                            </a>
                        </li>
                    @endauth
                </ul>

                {{-- Menu administrasi: hanya tampil untuk Waka/Admin.
                     Akses sebenarnya tetap dijaga middleware role:Waka/Admin di routes/web.php. --}}
                @if($isAdmin)
                    <span class="sidebar-label">Administrasi</span>
                    <ul>
                        <li>
                            <a href="{{ route('roles.index') }}"
                               class="sidebar-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Kelola Role</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('users.index') }}"
                               class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <span>Kelola User</span>
                            </a>
                        </li>
                    </ul>
                @endif
            </nav>

            <div class="sidebar-footer">
                @auth
                    <div class="sidebar-user">
                        <span class="sidebar-avatar" aria-hidden="true">{{ $initial }}</span>
                        <span class="sidebar-user-text">
                            <strong>{{ $displayName }}</strong>
                            <small>{{ $roleName ?: '-' }}</small>
                        </span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="sidebar-logout">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <span>Logout</span>
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="sidebar-link {{ request()->routeIs('login') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <span>Login</span>
                    </a>
                @endauth
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        {{-- ============================== KONTEN =============================== --}}
        <div class="app-content">
            {{-- Bilah atas hanya muncul di layar kecil, untuk membuka sidebar --}}
            <div class="topbar">
                <button type="button" class="topbar-toggle" id="sidebarToggle"
                        aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <strong class="topbar-title">RAPI-Sekolah</strong>
            </div>

            <main>
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            var body     = document.body;
            var toggle   = document.getElementById('sidebarToggle');
            var backdrop = document.getElementById('sidebarBackdrop');
            var sidebar  = document.getElementById('sidebar');

            function setOpen(open) {
                body.classList.toggle('sidebar-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () {
                setOpen(!body.classList.contains('sidebar-open'));
            });
            backdrop.addEventListener('click', function () { setOpen(false); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { setOpen(false); }
            });
            // Setelah memilih menu di layar kecil, tutup sidebar.
            sidebar.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', function () { setOpen(false); });
            });
        })();
    </script>
</body>
</html>
