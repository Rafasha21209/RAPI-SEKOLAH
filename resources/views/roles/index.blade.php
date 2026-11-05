@extends('layouts.app')

@section('content')
<div style="padding: 20px;">
    <h2>Kelola Role</h2>
    <p style="font-size: 12px; color: #555;">
        <a href="{{ route('events.index') }}">&larr; Kembali</a> &middot;
        <a href="{{ route('users.index') }}">Kelola User & Assign Role</a>
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

    <fieldset style="margin-bottom: 20px; padding: 15px; max-width: 400px;">
        <legend><strong>Tambah Role Baru</strong></legend>
        <form action="{{ route('roles.store') }}" method="POST">
            @csrf
            <p>
                <label>Nama Role:</label><br>
                <input type="text" name="role_name" value="{{ old('role_name') }}" required maxlength="50" style="width: 100%;">
            </p>
            <button type="submit">Tambah Role</button>
        </form>
    </fieldset>

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; max-width: 700px; border-collapse: collapse;">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Role</th>
                <th>Jumlah User</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($roles as $role)
                <tr>
                    <td data-label="ID">{{ $role->id }}</td>
                    <td data-label="Nama Role">
                        {{ $role->role_name }}
                        @if($role->isProtected())
                            <small style="color: #666;">(bawaan sistem)</small>
                        @endif
                    </td>
                    <td data-label="Jumlah User">{{ $role->users_count }}</td>
                    <td data-label="Aksi" class="td-actions">
                        @if($role->isProtected())
                            <small style="color: #666;">Tidak dapat diubah / dihapus</small>
                        @else
                            <details>
                                <summary style="font-size: 11px; cursor: pointer;">Edit</summary>
                                <form action="{{ route('roles.update', $role->id) }}" method="POST" style="margin-top: 5px;">
                                    @csrf
                                    @method('PUT')
                                    <input type="text" name="role_name" value="{{ $role->role_name }}" required maxlength="50" style="font-size: 11px; width: 130px;">
                                    <button type="submit" style="font-size: 11px;">Simpan</button>
                                </form>
                            </details>

                            <form action="{{ route('roles.destroy', $role->id) }}" method="POST" style="margin-top: 5px;" onsubmit="return confirm('Yakin ingin menghapus role ini?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="font-size: 11px; background: #6c757d; color: white; border: none; padding: 3px 6px;" @if($role->users_count > 0) title="Masih dipakai user" @endif>Hapus</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">Belum ada role.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
