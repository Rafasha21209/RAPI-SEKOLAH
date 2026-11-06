@extends('layouts.app')

@section('content')
@php
    $nama    = $user->fullname ?: $user->username;
    $inisial = mb_strtoupper(mb_substr($nama, 0, 1));
    $avatar  = $user->avatarUrl();
@endphp

<div style="padding: 20px;">
    <h2>Profil Saya</h2>

    @if(session('success'))
        <div style="color: green; margin-bottom: 10px;">{{ session('success') }}</div>
    @endif

    <div class="profile-card">
        <div class="profile-head">
            @if($avatar)
                <img class="profile-avatar profile-avatar-img" src="{{ $avatar }}" alt="Foto profil {{ $nama }}">
            @else
                <span class="profile-avatar" aria-hidden="true">{{ $inisial }}</span>
            @endif
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
            @if($user->isPic())
                <div><dt>Keterangan PIC</dt><dd class="pic-note-block">{{ $user->pic_note ?: 'Belum diisi' }}</dd></div>
            @endif
        </dl>

        <p style="margin-top: 14px;">
            <a href="{{ route('profile.edit') }}" class="btn-primary">Edit Profil</a>
        </p>

        <p class="profile-note">
            Email dipakai untuk menerima kode OTP saat lupa password. Role diatur oleh Waka/Admin (menu Kelola User).
        </p>
    </div>
</div>
@endsection
