@extends('layouts.app')

@section('content')
<div style="padding: 20px;">
    <h2>Notifikasi</h2>

    @if(session('success'))
        <div style="color: green; margin-bottom: 10px;">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div style="color: red; margin-bottom: 10px;">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Khusus Waka/Admin: kirim notifikasi ke PIC Ruang (hak akses dicek juga di backend) --}}
    @if(Auth::user()->isAdmin())
        <fieldset style="margin-bottom: 20px; padding: 15px;">
            <legend><strong>Kirim Notifikasi ke PIC</strong></legend>
            <form action="{{ route('notifications.send') }}" method="POST">
                @csrf
                <p>
                    <label>Tujuan:</label><br>
                    <select name="pic_id" required>
                        <option value="">-- Pilih PIC --</option>
                        <option value="all" {{ old('pic_id') === 'all' ? 'selected' : '' }}>Semua PIC ({{ $pics->count() }})</option>
                        @foreach($pics as $pic)
                            <option value="{{ $pic->id }}" {{ (string) old('pic_id') === (string) $pic->id ? 'selected' : '' }}>
                                {{ $pic->fullname ?: $pic->username }}
                                —
                                @if($pic->is_all_rooms_pic) semua ruangan
                                @elseif($pic->rooms->isNotEmpty()) {{ $pic->rooms->pluck('room_name')->implode(', ') }}
                                @else belum memegang ruangan
                                @endif
                            </option>
                        @endforeach
                    </select>
                </p>
                <p>
                    <label>Judul:</label><br>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="100" required style="width: 100%; max-width: 400px;">
                </p>
                <p>
                    <label>Pesan (minimal 5 karakter):</label><br>
                    <textarea name="message" rows="3" minlength="5" maxlength="500" required style="width: 100%; max-width: 400px;">{{ old('message') }}</textarea>
                </p>
                <button type="submit">Kirim Notifikasi</button>
            </form>
        </fieldset>
    @endif

    <form action="{{ route('notifications.read-all') }}" method="POST" style="margin-bottom: 12px;">
        @csrf
        <button type="submit">Tandai semua sudah dibaca</button>
    </form>

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr><th>Waktu</th><th>Judul</th><th>Pesan</th></tr>
        </thead>
        <tbody>
            @forelse($notifications as $n)
                @php
                    $t = mb_strtolower($n->title);
                    $color = str_contains($t, 'ditolak') ? '#c0392b' : (str_contains($t, 'disetujui') || str_contains($t, 'diperbaiki') || str_contains($t, 'terkirim') ? '#27ae60' : '#333');
                @endphp
                <tr @if(!$n->is_read) style="background:#f3f8ff; font-weight:600;" @endif>
                    <td data-label="Waktu">{{ $n->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                    <td data-label="Judul" style="color: {{ $color }};">{{ $n->title }}</td>
                    <td data-label="Pesan">{{ $n->message }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;">Belum ada notifikasi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
