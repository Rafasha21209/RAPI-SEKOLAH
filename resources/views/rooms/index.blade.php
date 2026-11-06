@extends('layouts.app')

@section('content')
<div style="padding: 20px;">
    <h2>Kelola Ruangan</h2>
    @include('partials.admin-nav', ['active' => 'rooms'])

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
        <legend><strong>Tambah Ruangan Baru</strong></legend>
        <form action="{{ route('rooms.store') }}" method="POST" autocomplete="off">
            @csrf
            <p>
                <label for="room_name">Nama Ruangan (mis. X RPL 1):</label><br>
                <input type="text" id="room_name" name="room_name" value="{{ old('room_name') }}" required maxlength="100" style="width: 100%;">
            </p>
            <p>
                <label for="room_type">Jenis Ruangan:</label><br>
                <select id="room_type" name="room_type" required>
                    @foreach($types as $type)
                        <option value="{{ $type }}" {{ old('room_type', 'Kelas') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </p>
            <p>
                <label for="capacity">Kapasitas (orang):</label><br>
                <input type="number" id="capacity" name="capacity" value="{{ old('capacity', 36) }}" required min="1" max="1000">
            </p>
            <p>
                <label for="location">Lokasi / Gedung:</label><br>
                <input type="text" id="location" name="location" value="{{ old('location', '') }}" required maxlength="150" style="width: 100%;">
            </p>
            <p>
                <label for="pic_id">PIC Ruangan (opsional):</label><br>
                <select id="pic_id" name="pic_id">
                    <option value="">-- Belum ditentukan --</option>
                    @foreach($pics as $pic)
                        <option value="{{ $pic->id }}" {{ old('pic_id') == $pic->id ? 'selected' : '' }}>{{ $pic->fullname ?: $pic->username }}</option>
                    @endforeach
                </select>
            </p>
            <button type="submit">Tambah Ruangan</button>
        </form>
    </fieldset>

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; max-width: 900px; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Nama Ruangan</th>
                <th>Jenis</th>
                <th>Kapasitas</th>
                <th>Lokasi</th>
                <th>PIC</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rooms as $room)
                <tr>
                    <td data-label="Nama Ruangan">{{ $room->room_name }}</td>
                    <td data-label="Jenis">{{ $room->room_type ?: '-' }}</td>
                    <td data-label="Kapasitas">{{ $room->capacity }}</td>
                    <td data-label="Lokasi">{{ $room->location }}</td>
                    <td data-label="PIC">
                        {{ $room->pic->fullname ?? ($room->pic->username ?? '') }}@unless($room->pic)<small>PIC semua ruangan</small>@endunless
                        @if($room->picNote())
                            <br><small class="pic-note">Keterangan: {{ $room->picNote() }}</small>
                        @endif
                    </td>
                    <td data-label="Status">{{ $room->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                    <td data-label="Aksi" class="td-actions">
                        <details>
                            <summary style="font-size: 11px; cursor: pointer;">Edit</summary>
                            <form action="{{ route('rooms.update', $room->id) }}" method="POST" style="margin-top: 5px;">
                                @csrf
                                @method('PUT')
                                <input type="text" name="room_name" value="{{ $room->room_name }}" required maxlength="100" style="font-size: 11px; width: 140px;"><br>
                                <select name="room_type" style="font-size: 11px; margin-top: 3px;">
                                    @foreach($types as $type)
                                        <option value="{{ $type }}" {{ $room->room_type === $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select><br>
                                <input type="number" name="capacity" value="{{ $room->capacity }}" required min="1" max="1000" style="font-size: 11px; width: 70px; margin-top: 3px;"><br>
                                <input type="text" name="location" value="{{ $room->location }}" required maxlength="150" style="font-size: 11px; width: 140px; margin-top: 3px;"><br>
                                <select name="pic_id" style="font-size: 11px; margin-top: 3px;">
                                    <option value="">-- Tanpa PIC --</option>
                                    @foreach($pics as $pic)
                                        <option value="{{ $pic->id }}" {{ $room->pic_id == $pic->id ? 'selected' : '' }}>{{ $pic->fullname ?: $pic->username }}</option>
                                    @endforeach
                                </select><br>
                                <button type="submit" style="font-size: 11px; margin-top: 3px;">Simpan</button>
                            </form>
                        </details>

                        <form action="{{ route('rooms.toggle', $room->id) }}" method="POST" style="margin-top: 5px;">
                            @csrf
                            @method('PUT')
                            <button type="submit" style="font-size: 11px;">{{ $room->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Belum ada ruangan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
