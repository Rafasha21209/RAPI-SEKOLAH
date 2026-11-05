@extends('layouts.app')

@section('content')
<style>
    .report-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 13px; }
    .report-table th, .report-table td { border: 1px solid #999; padding: 6px 8px; text-align: left; vertical-align: top; }
    .report-wrap { overflow-x: auto; }
    .report-actions a, .report-actions button { margin-right: 8px; }
    @media print {
        .sidebar, .topbar, .sidebar-backdrop, .no-print { display: none !important; }
        .app-content { margin-left: 0 !important; }
    }
</style>

<div style="padding: 20px;">
    <h2>Laporan {{ $mode === 'daily' ? 'Harian' : 'Bulanan' }}</h2>

    <form method="GET" action="{{ route('reports.index') }}" class="report-filter no-print">
        <div class="rf-field">
            <label for="mode">Jenis laporan</label>
            <select id="mode" name="mode" onchange="document.getElementById('f-daily').style.display=this.value==='daily'?'block':'none';document.getElementById('f-monthly').style.display=this.value==='daily'?'none':'block';">
                <option value="daily" {{ $mode === 'daily' ? 'selected' : '' }}>Harian</option>
                <option value="monthly" {{ $mode === 'monthly' ? 'selected' : '' }}>Bulanan</option>
            </select>
        </div>
        <div class="rf-field" id="f-daily" style="display: {{ $mode === 'daily' ? 'block' : 'none' }};">
            <label for="date">Tanggal</label>
            <input type="date" id="date" name="date" value="{{ $date }}">
        </div>
        <div class="rf-field" id="f-monthly" style="display: {{ $mode === 'daily' ? 'none' : 'block' }};">
            <label for="month">Bulan</label>
            <input type="month" id="month" name="month" value="{{ $month }}">
        </div>
        <button type="submit" class="rf-submit">Tampilkan</button>
    </form>

    <div class="report-actions no-print">
        <button type="button" class="ra-btn ra-print" onclick="window.print()">Cetak / Simpan PDF</button>
        <a class="ra-btn ra-csv" href="{{ route('reports.export', ['mode' => $mode, 'month' => $month, 'date' => $date]) }}">Unduh Excel (CSV)</a>
        <a class="ra-btn ra-wa" href="{{ $waUrl }}" target="_blank" rel="noopener">Kirim via WhatsApp</a>
    </div>

    <h3>Periode: {{ $periodLabel }}</h3>

    <p>
        <strong>Peminjaman ruangan:</strong>
        total {{ $eventStats['total'] }} &middot; disetujui {{ $eventStats['approved'] }}
        &middot; menunggu {{ $eventStats['pending'] }} &middot; ditolak {{ $eventStats['rejected'] }}
    </p>
    <p style="margin-bottom: 16px;">
        <strong>Pengaduan kerusakan:</strong>
        total {{ $complaintStats['total'] }}
        @foreach(\App\Models\Complaint::STATUSES as $status)
            &middot; {{ strtolower($status) }} {{ $complaintStats[$status] }}
        @endforeach
    </p>

    <h3>Peminjaman Ruangan</h3>
    <div class="report-wrap">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Tanggal</th><th>Sesi</th><th>Ruangan</th><th>Agenda</th>
                    <th>Peminjam</th><th>Status</th><th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $event->date->format('d/m/Y') }}</td>
                        <td>{{ $event->sessionLabel() }}</td>
                        <td>{{ $event->room->room_name ?? '-' }}</td>
                        <td>{{ $event->event_name }}</td>
                        <td>{{ $event->user->fullname ?? $event->user->username ?? '-' }}</td>
                        <td>{{ ucfirst($event->normalizedStatus() ?: 'pending') }}</td>
                        <td>
                            @if($event->reject_reason) Ditolak: {{ $event->reject_reason }} @endif
                            @if($event->move_reason) Dipindah: {{ $event->move_reason }} @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align: center;">Tidak ada peminjaman pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3>Pengaduan Kerusakan</h3>
    <div class="report-wrap">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Tanggal Lapor</th><th>Ruangan</th><th>Kerusakan</th>
                    <th>Pelapor</th><th>PIC</th><th>Status</th><th>Selesai</th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $complaint)
                    <tr>
                        <td>{{ $complaint->reported_at ? $complaint->reported_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') : '-' }}</td>
                        <td>{{ $complaint->room->room_name ?? '-' }}</td>
                        <td>{{ $complaint->description }}</td>
                        <td>{{ $complaint->reporter->fullname ?? '-' }}</td>
                        <td>{{ $complaint->pic->fullname ?? $complaint->room->pic->fullname ?? '-' }}</td>
                        <td>{{ $complaint->status_repair }}</td>
                        <td>{{ $complaint->repaired_at ? $complaint->repaired_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align: center;">Tidak ada pengaduan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
