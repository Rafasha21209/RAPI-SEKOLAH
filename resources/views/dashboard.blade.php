@extends('layouts.app')

@section('content')
<div class="container">

    @auth
        <div class="welcome-box">
            <span class="role-badge">{{ Auth::user()->role->role_name ?? '-' }}</span>
            <h1>Selamat Datang, {{ Auth::user()->fullname ?? Auth::user()->username }}</h1>
            <p>Sistem Rencana Agenda dan Penjadwalan Terintegrasi Fasilitas Sekolah</p>
        </div>

        {{-- RINGKASAN: angka dibatasi sesuai role (lihat DashboardController) --}}
        @php
            $personal = $mode === 'personal';
            $stats = [
                [$personal ? 'Agenda saya hari ini' : 'Agenda hari ini', $todayEvents->count(), route('calendar')],
                [$personal ? 'Agenda saya mendatang' : 'Agenda mendatang', $upcomingCount, route('events.index')],
                [$personal ? 'Pengaduan saya' : 'Pengaduan kerusakan', $complaintTotal, route('complaints.index', ['status' => ''])],
                ['Belum selesai', $complaintUnfinished, route('complaints.index')],
            ];
        @endphp
        <style>
            .dash-scope { margin: 0 0 var(--sp-3, 12px); font-size: 0.85rem; color: var(--c-ink-muted, #74847e); }
            .dash-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--sp-3, 12px); margin-bottom: var(--sp-4, 16px); }
            .dash-stat { display: block; text-decoration: none; background: var(--glass-1, #fff); border: 1px solid var(--line-strong, #d9e0dd);
                border-left: 3px solid var(--c-primary, #3f7a68); border-radius: var(--r-md, 10px); padding: 14px 16px; color: inherit; }
            .dash-stat:hover { background: var(--glass-hover, #f1f5f3); }
            .dash-stat strong { display: block; font-size: 1.7rem; line-height: 1.1; color: var(--c-ink, #16302a); }
            .dash-stat span { font-size: 0.82rem; color: var(--c-ink-soft, #4d5f59); }
            .dash-panels { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--sp-4, 16px); margin-bottom: var(--sp-4, 16px); }
            .dash-panel { background: var(--glass-1, #fff); border: 1px solid var(--line-strong, #d9e0dd); border-radius: var(--r-md, 10px); overflow: hidden; }
            .dash-panel header { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid var(--line-soft, #e6ebe9); }
            .dash-panel header h3 { margin: 0; font-size: 0.98rem; color: var(--c-ink, #16302a); }
            .dash-panel header a { font-size: 0.82rem; }
            .dash-empty { padding: 22px 16px; text-align: center; color: var(--c-ink-muted, #74847e); font-size: 0.88rem; }
            .dash-item { padding: 10px 16px; border-bottom: 1px solid var(--line-soft, #e6ebe9); }
            .dash-item:last-child { border-bottom: 0; }
            .dash-item strong { display: block; font-size: 0.92rem; }
            .dash-item small { color: var(--c-ink-muted, #74847e); }
            .dash-tag { display: inline-block; margin-left: 6px; padding: 0 7px; border-radius: 999px; font-size: 0.68rem; font-weight: 600;
                background: var(--c-warn-bg, rgba(197,149,47,.15)); color: var(--c-warn, #8a6420); }
        </style>
        <p class="dash-scope">Ringkasan: {{ $scope }}</p>
        <div class="dash-stats">
            @foreach($stats as [$label, $total, $url])
                <a class="dash-stat" href="{{ $url }}">
                    <strong>{{ $total }}</strong>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>

        <div class="dash-panels">
            <section class="dash-panel">
                <header>
                    <h3>{{ $personal ? 'Agenda saya hari ini' : 'Agenda hari ini' }}</h3>
                    <a href="{{ route('calendar') }}">Buka kalender</a>
                </header>
                @forelse($todayEvents as $ev)
                    <div class="dash-item">
                        <strong>{{ $ev->event_name }}@if($ev->isPending())<span class="dash-tag">Pending</span>@endif</strong>
                        <small>{{ $ev->sessionLabel() }} · {{ $ev->room->room_name ?? '-' }}</small>
                    </div>
                @empty
                    <div class="dash-empty">Tidak ada agenda hari ini.</div>
                @endforelse
            </section>

            <section class="dash-panel">
                <header>
                    <h3>{{ $personal ? 'Agenda saya mendatang' : 'Agenda mendatang' }}</h3>
                    <a href="{{ route('events.index') }}">Kelola</a>
                </header>
                @forelse($upcomingEvents as $ev)
                    <div class="dash-item">
                        <strong>{{ $ev->event_name }}@if($ev->isPending())<span class="dash-tag">Pending</span>@endif</strong>
                        <small>{{ $ev->date->format('d/m/Y') }}, {{ $ev->sessionLabel() }} · {{ $ev->room->room_name ?? '-' }}</small>
                    </div>
                @empty
                    <div class="dash-empty">Tidak ada agenda mendatang.</div>
                @endforelse
            </section>
        </div>

        <div class="card-grid">
            @if((Auth::user()->role->role_name ?? '') === 'Waka/Admin')
                <div class="card">
                    <h3>Kelola Agenda & Ruangan (Admin)</h3>
                    <p>Akses penuh CRUD, memindahkan ruangan (overriding), dan manajemen jadwal.</p>
                    <a href="{{ route('events.index') }}" class="btn-primary">Buka Modul Agenda &rarr;</a>
                </div>
                <div class="card">
                    <h3>Monitoring Pengaduan Kerusakan (Admin)</h3>
                    <p>Pantau seluruh laporan kerusakan dan kirim peringatan WhatsApp ke PIC untuk laporan yang melewati batas waktu. Perubahan status perbaikan dilakukan oleh PIC Ruang.</p>
                    <a href="{{ route('complaints.index') }}" class="btn-primary">Buka Modul Pengaduan &rarr;</a>
                </div>

            @elseif((Auth::user()->role->role_name ?? '') === 'PIC Ruang')
                <div class="card">
                    <h3>Agenda Ruangan</h3>
                    <p>Pantau jadwal agenda penggunaan ruangan di bawah pengawasan Anda.</p>
                    <a href="{{ route('events.index') }}" class="btn-primary">Buka Modul Agenda &rarr;</a>
                </div>
                <div class="card">
                    <h3>Kelola Status Pengaduan</h3>
                    <p>Perbarui status perbaikan kerusakan pada ruangan yang menjadi tanggung jawab Anda (Belum Diperbaiki &rarr; Menunggu Spare Part &rarr; Sudah Diperbaiki).</p>
                    <a href="{{ route('complaints.index') }}" class="btn-primary">Buka Modul Pengaduan &rarr;</a>
                </div>

            @elseif((Auth::user()->role->role_name ?? '') === 'Guru/Organisasi')
                <div class="card">
                    <h3>Pengajuan Agenda & Peminjaman</h3>
                    <p>Ajukan peminjaman ruangan baru (hanya mengajukan, tanpa hak edit/hapus jadwal).</p>
                    <a href="{{ route('events.index') }}" class="btn-primary">Buka Modul Agenda &rarr;</a>
                </div>
                <div class="card">
                    <h3>Buat Pengaduan Kerusakan</h3>
                    <p>Laporkan kerusakan fasilitas ruangan dengan melampirkan foto bukti.</p>
                    <a href="{{ route('complaints.index') }}" class="btn-primary">Buka Modul Pengaduan &rarr;</a>
                </div>

            @elseif((Auth::user()->role->role_name ?? '') === 'Siswa')
                <div class="card">
                    <h3>Pengajuan Peminjaman Ruangan</h3>
                    <p>Ajukan peminjaman ruangan untuk kegiatan siswa pada jam sekolah. Peminjaman di luar jam sekolah hanya dapat diajukan oleh guru.</p>
                    <a href="{{ route('events.index') }}" class="btn-primary">Buka Modul Agenda &rarr;</a>
                </div>
                <div class="card">
                    <h3>Kalender Sekolah</h3>
                    <p>Lihat agenda sekolah, kegiatan, dan hari libur nasional.</p>
                    <a href="{{ route('calendar') }}" class="btn-primary">Lihat Kalender &rarr;</a>
                </div>
            @endif
        </div>

    @else
        <div class="welcome-box">
            <h1>Selamat Datang di Sistem RAPI</h1>
            <p>Sistem Rencana Agenda dan Penjadwalan Terintegrasi Fasilitas Sekolah</p>
        </div>

        <div class="card-grid">
            <div class="card">
                <h3>Kalender Publik</h3>
                <p>Pantau jadwal penggunaan ruangan dan agenda kegiatan sekolah secara transparan.</p>
                <a href="{{ route('public.calendar') }}" class="btn-primary">Lihat Kalender &rarr;</a>
            </div>
            <div class="card">
                <h3>Login Sistem</h3>
                <p>Silakan masuk menggunakan akun terdaftar untuk mengakses fitur.</p>
                <a href="{{ route('login') }}" class="btn-primary">Login &rarr;</a>
            </div>
        </div>
    @endauth

</div>
@endsection
