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

    <h3>Peminjaman Ruangan <small style="font-weight:400; color:#666;">(terbaru di atas)</small></h3>
    <div class="report-wrap">
        <table class="table-stack report-table">
            <thead>
                <tr>
                    <th>No</th><th>Nama Pengajuan</th><th>Pengaju</th><th>Ruangan</th>
                    <th>Tanggal</th><th>Status</th><th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($events as $event)
                    @php $meta = $event->typeMeta(); $photoUrl = $event->photoUrl(); @endphp
                    <tr>
                        <td data-label="No">{{ $loop->iteration }}</td>
                        <td data-label="Nama Pengajuan">
                            <strong class="cell-title">{{ $event->event_name }}</strong><br>
                            <span class="cat-badge" style="background: {{ $meta['color'] }};">{{ $event->category ?: $meta['label'] }}</span>
                            @if($event->is_outside_hours)<span class="cat-badge" style="background:#6d4c41;">Luar jam sekolah</span>@endif
                        </td>
                        <td data-label="Pengaju">{{ $event->user->fullname ?? $event->user->username ?? '-' }}</td>
                        <td data-label="Ruangan">{{ $event->room->room_name ?? '-' }}</td>
                        <td data-label="Tanggal">
                            {{ $event->date->format('d/m/Y') }}<br>
                            <small>{{ $event->sessionLabel() }}</small>
                        </td>
                        <td data-label="Status">
                            <span>{{ $event->statusText() }}</span>
                            @if($event->reject_reason)<br><small>Ditolak: {{ $event->reject_reason }}</small>@endif
                            @if($event->move_reason)<br><small>Dipindah: {{ $event->move_reason }}</small>@endif
                        </td>
                        <td data-label="Aksi" class="td-actions no-print">
                            <button type="button" class="btn-detail" data-detail="{{ json_encode($event->detailPayload(), JSON_UNESCAPED_UNICODE) }}">Detail</button>
                            @if($photoUrl)
                                <a href="{{ $photoUrl }}" class="btn-detail btn-link" data-lightbox="rep-event-{{ $event->id }}" data-caption="Foto / dokumen izin — {{ $event->event_name }}">Foto Izin</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align: center;">Tidak ada peminjaman pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3>Pengaduan Kerusakan <small style="font-weight:400; color:#666;">(terbaru di atas)</small></h3>
    <div class="report-wrap">
        <table class="table-stack report-table">
            <thead>
                <tr>
                    <th>No</th><th>Tanggal Lapor</th><th>Ruangan</th><th>Kerusakan</th>
                    <th>Pelapor</th><th>PIC</th><th>Status</th><th>Selesai</th><th class="no-print">Foto</th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $complaint)
                    <tr>
                        <td data-label="No">{{ $loop->iteration }}</td>
                        <td data-label="Tanggal Lapor">{{ $complaint->reported_at ? $complaint->reported_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') : '-' }}</td>
                        <td data-label="Ruangan">{{ $complaint->room->room_name ?? '-' }}</td>
                        <td data-label="Kerusakan">{{ $complaint->description }}</td>
                        <td data-label="Pelapor">{{ $complaint->reporter->fullname ?? '-' }}</td>
                        <td data-label="PIC">
                            {{ $complaint->pic->fullname ?? $complaint->room->pic->fullname ?? '-' }}
                            @php $picNote = trim((string) (($complaint->pic ?? $complaint->room?->responsiblePic())?->pic_note ?? '')); @endphp
                            @if($picNote !== '')<br><small class="pic-note">{{ $picNote }}</small>@endif
                        </td>
                        <td data-label="Status"><span>{{ $complaint->status_repair }}</span></td>
                        <td data-label="Selesai">{{ $complaint->repaired_at ? $complaint->repaired_at->copy()->timezone('Asia/Jakarta')->format('d/m/Y H:i') : '-' }}</td>
                        <td data-label="Foto" class="td-actions no-print">
                            <div class="thumb-row">
                                @if($complaint->photo_path)
                                    <a href="{{ asset('storage/' . $complaint->photo_path) }}" class="thumb" data-lightbox="rep-complaint-{{ $complaint->id }}" data-caption="Foto kerusakan">
                                        <img src="{{ asset('storage/' . $complaint->photo_path) }}" alt="Foto kerusakan" loading="lazy"><small>Kerusakan</small>
                                    </a>
                                @endif
                                @if($complaint->repair_photo_path)
                                    <a href="{{ asset('storage/' . $complaint->repair_photo_path) }}" class="thumb" data-lightbox="rep-complaint-{{ $complaint->id }}" data-caption="Foto setelah diperbaiki">
                                        <img src="{{ asset('storage/' . $complaint->repair_photo_path) }}" alt="Foto setelah diperbaiki" loading="lazy"><small>Diperbaiki</small>
                                    </a>
                                @endif
                                @if(!$complaint->photo_path && !$complaint->repair_photo_path)-@endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align: center;">Tidak ada pengaduan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('partials.detail-modal')
@endsection
