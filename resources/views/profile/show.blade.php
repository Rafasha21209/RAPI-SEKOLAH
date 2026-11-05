@extends('layouts.app')

@section('content')
@php
    $nama    = $user->fullname ?: $user->username;
    $inisial = mb_strtoupper(mb_substr($nama, 0, 1));
@endphp

<div style="padding: 20px;">
    <h2>Profil Saya</h2>

    <div class="profile-card">
        <div class="profile-head">
            <span class="profile-avatar" aria-hidden="true">{{ $inisial }}</span>
            <div>
                <strong class="profile-name">{{ $nama }}</strong>
                <span class="profile-role">{{ $user->role->role_name ?? '-' }}</span>
            </div>
        </div>

        <dl class="profile-list">
            <div><dt>Nama</dt><dd>{{ $user->fullname ?: '-' }}</dd></div>
            <div><dt>Username</dt><dd>{{ $user->username }}</dd></div>
            <div><dt>Email</dt><dd>{{ $user->email ?: 'Belum diisi' }}</dd></div>
            <div><dt>Nomor HP</dt><dd>{{ $user->phone_number ?: 'Belum diisi' }}</dd></div>
            <div><dt>Role</dt><dd>{{ $user->role->role_name ?? '-' }}</dd></div>
        </dl>

        <p class="profile-note">
            Email dipakai untuk menerima kode OTP saat lupa password. Untuk mengubah data profil,
            hubungi Waka/Admin (menu Kelola User).
        </p>
    </div>
</div>
@endsection
