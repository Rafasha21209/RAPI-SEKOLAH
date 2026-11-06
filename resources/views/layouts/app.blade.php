@php
    $authUser  = Auth::user();
    $roleName  = $authUser?->role?->role_name;
    $isAdmin   = $roleName === 'Waka/Admin';
    $displayName = $authUser ? ($authUser->fullname ?: $authUser->username) : null;
    $initial   = $displayName ? mb_strtoupper(mb_substr($displayName, 0, 1)) : '';
    $avatarUrl = $authUser?->avatarUrl();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RAPI-Sekolah</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mobile-polish.css') }}">
    {{-- Agar bisa di-install di HP (Add to Home Screen) --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#3f7a68">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">
</head>
<body class="@yield('body_class')">
    <div class="app-shell">

        {{-- ============================== SIDEBAR ============================== --}}
        <aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
            <a href="{{ route('calendar') }}" class="sidebar-brand">
                <img class="sidebar-logo" src="{{ asset('images/logo-smkn1-bantul.png') }}" alt="Logo SMK Negeri 1 Bantul">
                <span class="sidebar-brand-text">
                    <strong>RAPI-Sekolah</strong>
                    <small>SMK Negeri 1 Bantul</small>
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
                        <li>
                            @php $unreadNotif = \App\Models\Notification::where('user_id', Auth::id())->where('is_read', false)->count(); @endphp
                            <a href="{{ route('notifications.index') }}"
                               class="sidebar-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                <span>Notifikasi @if($unreadNotif > 0)<strong style="color:#e74c3c;">({{ $unreadNotif }})</strong>@endif</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('profile.show') }}"
                               class="sidebar-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>Profil Saya</span>
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
                            <a href="{{ route('rooms.index') }}"
                               class="sidebar-link {{ request()->routeIs('rooms.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><rect x="10" y="13" width="4" height="8"/></svg>
                                <span>Kelola Ruangan</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reports.index') }}"
                               class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <span>Laporan Harian &amp; Bulanan</span>
                            </a>
                        </li>
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
                    <a href="{{ route('profile.show') }}" class="sidebar-user" style="text-decoration:none; color:inherit;" title="Lihat profil">
                        @if($avatarUrl)
                            <img class="sidebar-avatar sidebar-avatar-img" src="{{ $avatarUrl }}" alt="" aria-hidden="true">
                        @else
                            <span class="sidebar-avatar" aria-hidden="true">{{ $initial }}</span>
                        @endif
                        <span class="sidebar-user-text">
                            <strong>{{ $displayName }}</strong>
                            <small>{{ $roleName ?: '-' }}</small>
                        </span>
                    </a>
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
                <img class="topbar-logo" src="{{ asset('images/logo-smkn1-bantul.png') }}" alt="Logo SMK Negeri 1 Bantul">
                <strong class="topbar-title">RAPI-Sekolah</strong>
            </div>

            <main>
                @yield('content')
            </main>
        </div>
    </div>

    {{-- ===================== NAVIGASI BAWAH (khusus HP, setelah login) ===================== --}}
    @auth
        <nav class="bottom-nav" aria-label="Navigasi cepat">
            <a href="{{ route('calendar') }}" class="{{ request()->routeIs('calendar', 'public.calendar') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span>Kalender</span>
            </a>
            <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                <span>Agenda</span>
            </a>
            <a href="{{ route('complaints.index') }}" class="{{ request()->routeIs('complaints.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span>Pengaduan</span>
            </a>
            <a href="{{ route('notifications.index') }}" class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <span>Notifikasi</span>
                @if(($unreadNotif ?? 0) > 0)<em class="bn-badge">{{ $unreadNotif > 9 ? '9+' : $unreadNotif }}</em>@endif
            </a>
            <button type="button" id="bottomMenuBtn">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                <span>Menu</span>
            </button>
        </nav>
    @endauth

    {{-- Pratinjau foto di dalam aplikasi (lightbox) untuk semua halaman --}}
    @include('partials.photo-lightbox')

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
        }
    </script>

    <script>
        (function () {
            var menuBtn = document.getElementById('bottomMenuBtn');
            if (menuBtn) {
                menuBtn.addEventListener('click', function () {
                    document.getElementById('sidebarToggle').click();
                });
            }

            // Beri warna lencana pada kolom Status (hanya yang berisi teks biasa).
            var map = [
                [/menunggu|pending|belum diperbaiki|spare/i, 'badge-warn'],
                [/disetujui|approved|sudah diperbaiki|aktif$/i, 'badge-ok'],
                [/ditolak|rejected|nonaktif/i, 'badge-danger'],
                [/sedang/i, 'badge-info']
            ];
            function pick(text) {
                var cls = 'badge-neutral';
                map.forEach(function (m) { if (cls === 'badge-neutral' && m[0].test(text)) { cls = m[1]; } });
                return cls;
            }
            document.querySelectorAll('td[data-label="Status"]').forEach(function (td) {
                var el = td.querySelector(':scope > span, :scope > strong');
                if (el) {
                    el.removeAttribute('style');
                    el.classList.add('status-badge', pick(el.textContent.trim()));
                    return;
                }
                var n = td.firstChild;
                if (n && n.nodeType === 3 && n.nodeValue.trim()) {
                    var span = document.createElement('span');
                    span.textContent = n.nodeValue.trim();
                    span.className = 'status-badge ' + pick(span.textContent);
                    td.replaceChild(span, n);
                }
            });
        })();
    </script>

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
