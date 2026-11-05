@extends('layouts.app')

@section('content')
<div style="padding: 20px;">
    <h2>Notifikasi</h2>

    @if(session('success'))
        <div style="color: green; margin-bottom: 10px;">{{ session('success') }}</div>
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
