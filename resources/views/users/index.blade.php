@extends('layouts.app')

@section('content')
<div style="padding: 20px;">
    <h2>Kelola User & Assign Role</h2>
    <p style="font-size: 12px; color: #555;">
        <a href="{{ route('events.index') }}">&larr; Kembali</a> &middot;
        <a href="{{ route('roles.index') }}">Kelola Role</a>
    </p>

    @if(session('success'))
        <div style="color: green; margin-bottom: 10px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="color: red; margin-bottom: 10px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div style="color: red; margin-bottom: 10px;">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <fieldset style="margin-bottom: 20px; padding: 15px; max-width: 460px;">
        <legend><strong>Tambah User Baru</strong></legend>
        <form action="{{ route('users.store') }}" method="POST" autocomplete="off">
            @csrf
            <p>
                <label for="fullname">Nama Lengkap:</label><br>
                <input type="text" id="fullname" name="fullname" value="{{ old('fullname') }}" required maxlength="100" style="width: 100%;">
            </p>
            <p>
                <label for="username">Username (untuk login):</label><br>
                <input type="text" id="username" name="username" value="{{ old('username') }}" required maxlength="50" pattern="[A-Za-z0-9._-]+" title="Huruf, angka, titik, garis bawah, dan strip (tanpa spasi)" style="width: 100%;">
            </p>
            <p>
                <label for="phone_number">Nomor HP / WhatsApp (opsional):</label><br>
                <input type="text" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" maxlength="20" placeholder="08xxxxxxxxxx" style="width: 100%;">
            </p>
            <p>
                <label for="email">Email (untuk kode OTP lupa password):</label><br>
                <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="150" placeholder="nama@gmail.com" style="width: 100%;">
            </p>
            <p>
                <label for="role_id">Role:</label><br>
                <select id="role_id" name="role_id" required>
                    <option value="">-- Pilih Role --</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->role_name }}</option>
                    @endforeach
                </select>
            </p>
            <p>
                <label><input type="checkbox" name="is_all_rooms_pic" value="1" {{ old('is_all_rooms_pic') ? 'checked' : '' }}> PIC semua ruangan (khusus role PIC Ruang)</label>
            </p>
            <p>
                <label for="password">Password (minimal 8 karakter):</label><br>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" style="width: 100%;">
            </p>
            <p>
                <label for="password_confirmation">Ulangi Password:</label><br>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password" style="width: 100%;">
            </p>
            <button type="submit">Tambah User</button>
        </form>
    </fieldset>

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; max-width: 900px; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Username</th>
                <th>No. HP</th>
                <th>Email</th>
                <th>Role Saat Ini</th>
                <th>Ganti Role</th>
                <th>Cakupan PIC</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td data-label="Nama">{{ $user->fullname ?? '-' }}</td>
                    <td data-label="Username">{{ $user->username }}</td>
                    <td data-label="No. HP">{{ $user->phone_number ?: '-' }}</td>
                    <td data-label="Email">{{ $user->email ?: '-' }}</td>
                    <td data-label="Role">{{ $user->role->role_name ?? '-' }}</td>
                    <td data-label="Ganti Role" class="td-actions">
                        <form action="{{ route('users.update-role', $user->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <select name="role_id" style="font-size: 11px;">
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" {{ $user->role_id == $role->id ? 'selected' : '' }}>{{ $role->role_name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" style="font-size: 11px;">Simpan</button>
                        </form>
                    </td>
                    <td data-label="Cakupan PIC" class="td-actions">
                        @if(($user->role->role_name ?? '') === 'PIC Ruang')
                            <form action="{{ route('users.update-pic-scope', $user->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <label style="font-size: 11px;">
                                    <input type="checkbox" name="is_all_rooms_pic" value="1" {{ $user->is_all_rooms_pic ? 'checked' : '' }}> Semua ruangan
                                </label>
                                <button type="submit" style="font-size: 11px;">Simpan</button>
                            </form>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Belum ada user.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
