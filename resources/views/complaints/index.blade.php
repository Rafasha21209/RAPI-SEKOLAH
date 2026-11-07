@extends('layouts.app')

@section('content')
@php
/** Konstanta status & batas waktu diambil dari Model agar tidak ada nilai kembar di view. */
$STATUS_BELUM = \App\Models\Complaint::STATUS_BELUM;
$DEADLINE_HOURS = \App\Models\Complaint::REPAIR_DEADLINE_HOURS;

$me = Auth::user();
$roleName = $me->role->role_name ?? '';
$isAdmin = $roleName === 'Waka/Admin';
$isPic = $roleName === 'PIC Ruang';
$canReport = in_array($roleName, ['Guru/Organisasi', 'Waka/Admin'], true);
@endphp

<div style="padding: 20px;">
    <h2>Modul Pengaduan Kerusakan Fasilitas</h2>
    <p style="font-size: 12px; color: #555;">
        Login sebagai <strong>{{ $me->fullname ?? $me->username }}</strong> ({{ $roleName ?: '-' }}).
        Batas waktu penanganan pengaduan: <strong>{{ $DEADLINE_HOURS }} jam</strong> sejak waktu lapor.
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

    {{-- ============================================================
         A. FORM PENGADUAN — Guru/Organisasi (dan Waka/Admin)
         Tombol disembunyikan di sini, tapi hak akses tetap
         divalidasi di backend (route + ComplaintPolicy::create).
    ============================================================ --}}
    @if($canReport)
    <fieldset style="margin-bottom: 20px; padding: 15px;">
        <legend><strong>Laporkan Kerusakan Fasilitas Ruangan</strong></legend>
        <form action="{{ route('complaints.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p>
                <label>Pilih Ruangan:</label><br>
                <select name="room_id" required>
                    <option value="">-- Pilih Ruangan --</option>
                    @foreach($rooms as $room)
                    <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                        {{ $room->room_name }}
                        @if($room->responsiblePic()) — PIC: {{ $room->responsiblePic()->fullname }} @else — PIC belum ditentukan @endif
                    </option>
                    @endforeach
                </select>
            </p>
            <p>
                <label>Deskripsi Kerusakan (Minimal 10 Karakter):</label><br>
                <textarea name="description" rows="3" minlength="10" required
                    style="width: 100%; max-width: 400px;">{{ old('description') }}</textarea>
            </p>
            <p>
                <label>Foto Bukti Kerusakan (JPG/PNG):</label><br>
                @include('partials.photo-input', ['name' => 'photo', 'required' => true])
            </p>
            <button type="submit">Kirim Pengaduan</button>
            <small style="display:block; color:#666; margin-top:6px;">
                Status awal pengaduan otomatis <strong>Belum Diperbaiki</strong> dan diteruskan ke PIC ruangan.
            </small>
        </form>
    </fieldset>
    @endif

    <h3>Daftar Pengaduan Sarana &amp; Prasarana <small style="font-weight:400; color:#666;">(terbaru di atas)</small></h3>
    @if($isPic)
    <p style="font-size: 12px; color: #555;">
        Anda hanya melihat pengaduan dari ruangan yang menjadi tanggung jawab Anda.
    </p>
    @elseif($isAdmin)
    <p style="font-size: 12px; color: #555;">
        Mode monitoring: WA Warning hanya aktif untuk pengaduan "Belum Diperbaiki" yang sudah
        melewati batas waktu. Perubahan status perbaikan dilakukan oleh PIC Ruang.
    </p>
    @endif

    {{-- Ringkasan per status: ketuk untuk menyaring cepat. Default daftar = Belum Diperbaiki. --}}
    @php
    $chips = [
    'belum' => ['Belum Diperbaiki', 'badge-warn', $counts[\App\Models\Complaint::STATUS_BELUM] ?? 0],
    'sparepart' => ['Menunggu Spare Part', 'badge-info', $counts[\App\Models\Complaint::STATUS_SPAREPART] ?? 0],
    'selesai' => ['Sudah Diperbaiki', 'badge-ok', $counts[\App\Models\Complaint::STATUS_SELESAI] ?? 0],
    ];
    $filterAktif = $keyword !== '' || $roomId > 0 || $status !== 'belum';
    @endphp
    <div class="complaint-chips" style="display:flex; flex-wrap:wrap; gap:6px; margin:6px 0 4px;">
        @foreach($chips as $key => [$label, $cls, $total])
        <a href="{{ route('complaints.index', ['status' => $key]) }}"
            class="status-badge {{ $cls }}"
            style="text-decoration:none; {{ $status === $key ? 'outline:2px solid currentColor; outline-offset:1px;' : '' }}">
            {{ $label }}: <strong>{{ $total }}</strong>
        </a>
        @endforeach
    </div>

    {{-- Filter: data lama tetap dapat dicari / dibuka lewat "Semua status" & halaman berikutnya --}}
    <form method="GET" action="{{ route('complaints.index') }}" class="list-filter">
        <input type="search" name="q" value="{{ $keyword }}" placeholder="Cari kerusakan, pelapor, atau ruangan" aria-label="Cari">
        <select name="status" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="belum" {{ $status === 'belum'     ? 'selected' : '' }}>Belum Diperbaiki</option>
            <option value="sparepart" {{ $status === 'sparepart' ? 'selected' : '' }}>Menunggu Spare Part</option>
            <option value="selesai" {{ $status === 'selesai'   ? 'selected' : '' }}>Sudah Diperbaiki</option>
        </select>
        <select name="room" aria-label="Filter ruangan">
            <option value="">Semua ruangan</option>
            @foreach($rooms as $r)
            <option value="{{ $r->id }}" {{ $roomId === (int) $r->id ? 'selected' : '' }}>{{ $r->room_name }}</option>
            @endforeach
        </select>
        <button type="submit">Terapkan</button>
        @if($filterAktif)
        <a href="{{ route('complaints.index') }}" class="list-filter-reset">Reset</a>
        @endif
    </form>

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>No</th>
                <th>Waktu Lapor</th>
                <th>Ruangan</th>
                <th>Pelapor</th>
                <th>Kerusakan</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($complaints as $complaint)
            @php
            $overdue = $complaint->isOverdue();
            $nextStatuses = $complaint->allowedNextStatuses();
            $bisaUbah = $me->can('updateStatus', $complaint) && count($nextStatuses) > 0;
            $bisaWa = $me->can('sendWaWarning', $complaint) && $complaint->canReceiveWaWarning();
            $bisaArsip = $isAdmin && $me->can('delete', $complaint);
            $adaKelola = $bisaUbah || $bisaArsip;
            $waktuLapor = $complaint->reported_at ?? $complaint->created_at;
            $picRuang = $complaint->pic ?? $complaint->room?->responsiblePic();
            $statusColor = match ($complaint->status_repair) {
            $STATUS_BELUM => '#b8860b',
            \App\Models\Complaint::STATUS_SPAREPART => '#b8860b',
            default => '#2e7d32',
            };

            // Isi modal "Detail": semua keterangan lengkap + foto, agar baris daftar tetap ringkas.
            $payload = [
            'title' => 'Pengaduan: ' . ($complaint->room->room_name ?? 'Ruangan'),
            'badges' => array_values(array_filter([
            ['text' => $complaint->status_repair, 'color' => $statusColor],
            $overdue ? ['text' => 'Lewat batas waktu', 'color' => '#6d4c41'] : null,
            ])),
            'rows' => [
            ['Waktu lapor', $waktuLapor ? $waktuLapor->format('d/m/Y H:i') : null],
            ['Ruangan', ($complaint->room->room_name ?? '-') . ($complaint->room?->location ? ' (' . $complaint->room->location . ')' : '')],
            ['Pelapor', $complaint->reporter->fullname ?? $complaint->reporter->username ?? '-'],
            ['Kerusakan', $complaint->description],
            ['Dari peminjaman', $complaint->event ? $complaint->event->event_name . ' (' . $complaint->event->date->format('d/m/Y') . ', ' . $complaint->event->sessionLabel() . ')' : null],
            ['PIC ruangan', $picRuang ? ($picRuang->fullname ?: $picRuang->username) : 'Belum ditentukan'],
            ['Keterangan PIC', $picRuang?->pic_note],
            ['Ditangani oleh', $complaint->handler?->fullname],
            ['Selesai pada', $complaint->repaired_at?->format('d/m/Y H:i')],
            ['Batas waktu penanganan', $complaint->isSelesai() ? null : $complaint->repairDeadline()?->format('d/m/Y H:i')],
            ['WA Warning', $complaint->warning_count > 0 ? $complaint->warning_count . 'x' . ($complaint->last_warned_at ? ' (terakhir ' . $complaint->last_warned_at->format('d/m H:i') . ')' : '') : null],
            ],
            'photos' => array_values(array_filter([
            $complaint->photo_path ? ['src' => asset('storage/' . $complaint->photo_path), 'caption' => 'Foto kerusakan'] : null,
            $complaint->repair_photo_path ? ['src' => asset('storage/' . $complaint->repair_photo_path), 'caption' => 'Foto setelah diperbaiki'] : null,
            ])),
            ];
            @endphp
            <tr>
                <td data-label="No">{{ $complaints->firstItem() + $loop->index }}</td>
                <td data-label="Waktu Lapor">
                    {{ $waktuLapor ? $waktuLapor->format('d/m/Y H:i') : '-' }}
                    @if($overdue)
                    <br><small style="color:#b8860b;">Lewat batas waktu</small>
                    @endif
                </td>
                <td data-label="Ruangan">
                    <strong class="cell-title">{{ $complaint->room->room_name ?? '-' }}</strong>
                    @if($picRuang)
                    <br><small>PIC Ruang: {{ $picRuang->fullname ?? $picRuang->username }}</small>
                    @else
                    <br><small style="color:#c0392b;">PIC belum ditentukan</small>
                    @endif
                </td>
                <td data-label="Pelapor">{{ $complaint->reporter->fullname ?? '-' }}</td>
                <td data-label="Kerusakan">
                    {{ \Illuminate\Support\Str::limit($complaint->description, 80) }}
                    @if($complaint->event)
                    <br><small style="color:#555;">Dari peminjaman: {{ $complaint->event->event_name }}</small>
                    @endif
                </td>
                <td data-label="Status">
                    <strong data-status="{{ $complaint->status_repair }}" @if($complaint->status_repair === $STATUS_BELUM) style="color:#8a5a00; background:#fff6e0; border-color:#f0d9a8;"@endif>{{ $complaint->status_repair }}</strong>
                    @if($complaint->repaired_at)
                    <br><small>Selesai: {{ $complaint->repaired_at->format('d/m/Y H:i') }}</small>
                    @endif
                    @if($complaint->warning_count > 0)
                    <br><small style="color:#666;">WA Warning: {{ $complaint->warning_count }}x</small>
                    @endif
                </td>
                <td data-label="Aksi" class="td-actions">
                    <button type="button" class="btn-detail" data-detail="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">Detail</button>
                    @if($complaint->photo_path)
                    <a href="{{ asset('storage/' . $complaint->photo_path) }}" class="btn-detail btn-link" data-lightbox="complaint-{{ $complaint->id }}" data-caption="Foto kerusakan">Foto</a>
                    @endif
                    @if($complaint->repair_photo_path)
                    <a href="{{ asset('storage/' . $complaint->repair_photo_path) }}" class="btn-detail btn-link" data-lightbox="complaint-{{ $complaint->id }}" data-caption="Foto setelah diperbaiki">Foto Perbaikan</a>
                    @endif

                    {{-- Waka/Admin: WA Warning hanya jika Belum Diperbaiki & lewat batas waktu --}}
                    @if($isAdmin && $bisaWa)
                    <a href="{{ route('complaints.wa-warning', $complaint->id) }}" target="_blank"
                        style="font-size: 11px; background: #25D366; color: white; padding: 3px 6px; text-decoration: none; border-radius: 3px;">
                        Kirim WA Warning
                    </a>
                    @endif

                    @if($adaKelola)
                    <details class="kelola">
                        <summary>Kelola</summary>

                        {{-- PIC Ruang: ubah status pengaduan ruangan tanggung jawabnya --}}
                        @if($bisaUbah)
                        <form action="{{ route('complaints.update-status', $complaint->id) }}" method="POST" enctype="multipart/form-data" style="margin-bottom: 5px;">
                            @csrf
                            @method('PUT')
                            <select name="status_repair" required style="font-size: 11px;" onchange="var f=this.form.querySelector('.repair-photo'); var done=this.value==='Sudah Diperbaiki'; f.style.display=done?'block':'none'; f.querySelector('input').required=done;">
                                @foreach($nextStatuses as $st)
                                <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="note" placeholder="Catatan (opsional)" maxlength="255" style="font-size: 11px; width: 130px;">
                            <div class="repair-photo" style="display:none; margin-top:4px;">
                                <small>Foto bukti perbaikan (wajib):</small><br>
                                @include('partials.photo-input', ['name' => 'repair_photo', 'required' => false])
                            </div>
                            <button type="submit" style="font-size: 11px;">Update Status</button>
                        </form>
                        @endif

                        @if($bisaArsip)
                        <form action="{{ route('complaints.destroy', $complaint->id) }}" method="POST"
                            onsubmit="return confirm('Arsipkan pengaduan ini? Data histori tetap tersimpan.');"
                            style="margin-top: 5px;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="font-size: 11px;">Arsipkan</button>
                        </form>
                        @endif
                    </details>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" style="text-align: center;">
                    @if($status === 'belum' && $keyword === '' && $roomId === 0)
                    Tidak ada pengaduan yang berstatus "Belum Diperbaiki". Pilih "Semua status" untuk melihat riwayat.
                    @elseif($filterAktif)
                    Tidak ada pengaduan yang cocok dengan filter.
                    @else
                    Belum ada data pengaduan kerusakan.
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{ $complaints->links() }}
</div>
<script>
    document.querySelectorAll('select[name="status_repair"]').forEach(function(el) {
        el.dispatchEvent(new Event('change'));
    });
</script>
@include('partials.detail-modal')
@endsection