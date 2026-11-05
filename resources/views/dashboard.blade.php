@extends('layouts.app')

@section('content')
<div class="container">

    @auth
        <div class="welcome-box">
            <span class="role-badge">{{ Auth::user()->role->role_name ?? '-' }}</span>
            <h1>Selamat Datang, {{ Auth::user()->fullname ?? Auth::user()->username }}</h1>
            <p>Sistem Rencana Agenda dan Penjadwalan Terintegrasi Fasilitas Sekolah</p>
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
