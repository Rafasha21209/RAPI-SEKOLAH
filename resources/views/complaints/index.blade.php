@extends('layouts.app')

@section('content')
@php
    /** Konstanta status & batas waktu diambil dari Model agar tidak ada nilai kembar di view. */
    $STATUS_BELUM     = \App\Models\Complaint::STATUS_BELUM;
    $DEADLINE_HOURS   = \App\Models\Complaint::REPAIR_DEADLINE_HOURS;

    $me       = Auth::user();
    $roleName = $me->role->role_name ?? '';
    $isAdmin  = $roleName === 'Waka/Admin';
    $isPic    = $roleName === 'PIC Ruang';
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
                    <label>Foto Bukti Kerusakan (JPG/PNG, maks 2 MB):</label><br>
                    @include('partials.photo-input', ['name' => 'photo', 'required' => true])
                </p>
                <button type="submit">Kirim Pengaduan</button>
                <small style="display:block; color:#666; margin-top:6px;">
                    Status awal pengaduan otomatis <strong>Belum Diperbaiki</strong> dan diteruskan ke PIC ruangan.
                </small>
            </form>
        </fieldset>
    @endif

    <h3>Daftar Pengaduan Sarana &amp; Prasarana</h3>
    @if($isPic)
        <p style="font-size: 12px; color: #555;">
            Anda hanya melihat pengaduan dari ruangan yang menjadi tanggung jawab Anda.
        </p>
    @elseif($isAdmin)
        <p style="font-size: 12px; color: #555;">
            Mode monitoring: Anda dapat mengirim WA Warning untuk pengaduan
            berstatus "Belum Diperbaiki" yang sudah melewati batas waktu. Perubahan status
            perbaikan dilakukan oleh PIC Ruang.
        </p>
    @endif

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Waktu Lapor</th>
                <th>Ruangan</th>
                <th>Pelapor</th>
                <th>Kerusakan</th>
                <th>Foto Bukti</th>
                <th>Status Perbaikan</th>
                <th>Kelola Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($complaints as $complaint)
                @php
                    $overdue      = $complaint->isOverdue();
                    $nextStatuses = $complaint->allowedNextStatuses();
                    $bisaUbah     = $me->can('updateStatus', $complaint) && count($nextStatuses) > 0;
                    $bisaWa       = $me->can('sendWaWarning', $complaint) && $complaint->canReceiveWaWarning();
                    $bisaArsip    = $me->can('delete', $complaint);
                    $waktuLapor   = $complaint->reported_at ?? $complaint->created_at;
                @endphp
                <tr @if($overdue) style="background: #fff4f4;" @endif>
                    <td data-label="Waktu Lapor">
                        {{ $waktuLapor ? $waktuLapor->format('d/m/Y H:i') : '-' }}
                        @if($overdue)
                            <br><small style="color:#c0392b;"><strong>Melewati batas waktu</strong></small>
                        @endif
                    </td>
                    <td data-label="Ruangan">
                        {{ $complaint->room->room_name ?? '-' }}
                        @if($complaint->pic ?? $complaint->room?->responsiblePic())
                            <br><small>PIC Ruang: {{ $complaint->pic->fullname ?? $complaint->room->responsiblePic()->fullname }}</small>
                        @else
                            <br><small style="color:#c0392b;">PIC belum ditentukan</small>
                        @endif
                    </td>
                    <td data-label="Pelapor">{{ $complaint->reporter->fullname ?? '-' }}</td>
                    <td data-label="Kerusakan">
                        {{ $complaint->description }}
                        @if($complaint->event)
                            <br><small style="color:#555;">Dari peminjaman: {{ $complaint->event->event_name }} ({{ $complaint->event->date->format('d/m/Y') }}, {{ $complaint->event->sessionLabel() }})</small>
                        @endif
                    </td>
                    <td data-label="Foto Bukti">
                        @if($complaint->photo_path)
                            <a href="{{ asset('storage/' . $complaint->photo_path) }}" target="_blank">Foto kerusakan</a>
                        @else
                            Tidak ada foto
                        @endif
                        @if($complaint->repair_photo_path)
                            <br><a href="{{ asset('storage/' . $complaint->repair_photo_path) }}" target="_blank" style="color:#27ae60;">Foto setelah diperbaiki</a>
                        @endif
                    </td>
                    <td data-label="Status">
                        <strong data-status="{{ $complaint->status_repair }}">{{ $complaint->status_repair }}</strong>
                        @if($complaint->handler)
                            <br><small>Ditangani: {{ $complaint->handler->fullname }}</small>
                        @endif
                        @if($complaint->repaired_at)
                            <br><small>Selesai: {{ $complaint->repaired_at->format('d/m/Y H:i') }}</small>
                        @endif
                        @if($complaint->warning_count > 0)
                            <br><small style="color:#c0392b;">
                                WA Warning: {{ $complaint->warning_count }}x
                                @if($complaint->last_warned_at)
                                    (terakhir {{ $complaint->last_warned_at->format('d/m H:i') }})
                                @endif
                            </small>
                        @endif
                    </td>
                    <td data-label="Aksi" class="td-actions">
                        {{-- PIC Ruang: ubah status pengaduan ruangan tanggung jawabnya --}}
                        @if($bisaUbah)
                            <form action="{{ route('complaints.update-status', $complaint->id) }}" method="POST" enctype="multipart/form-data" style="margin-bottom: 5px;">
                                @csrf
                                @method('PUT')
                                <select name="status_repair" required style="font-size: 11px;" onchange="var f=this.form.querySelector('.repair-photo'); var done=this.value==='Sudah Diperbaiki'; f.style.display=done?'block':'none'; f.querySelector('input').required=done;">
                                    @foreach($nextStatuses as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="note" placeholder="Catatan (opsional)" maxlength="255" style="font-size: 11px; width: 130px;">
                                <div class="repair-photo" style="display:none; margin-top:4px;">
                                    <small>Foto bukti perbaikan (wajib):</small><br>
                                    @include('partials.photo-input', ['name' => 'repair_photo', 'required' => false])
                                </div>
                                <button type="submit" style="font-size: 11px;">Update Status</button>
                            </form>
                        @elseif($isPic && $complaint->isSelesai())
                            <small style="color:#27ae60;">Selesai — tidak ada aksi lanjutan.</small>
                        @endif

                        {{-- Waka/Admin: WA Warning hanya jika Belum Diperbaiki & lewat batas waktu --}}
                        @if($isAdmin)
                            @if($bisaWa)
                                <a href="{{ route('complaints.wa-warning', $complaint->id) }}" target="_blank"
                                   style="font-size: 11px; background: #25D366; color: white; padding: 3px 6px; text-decoration: none; border-radius: 3px;">
                                    Kirim WA Warning
                                </a>
                            @elseif($complaint->isSelesai())
                                <small style="color:#27ae60;">Sudah diperbaiki — warning tidak diperlukan.</small>
                            @elseif($complaint->status_repair !== $STATUS_BELUM)
                                <small style="color:#666;">Sedang ditangani PIC ({{ $complaint->status_repair }}).</small>
                            @elseif(!$overdue)
                                <small style="color:#666;">
                                    Belum lewat batas waktu
                                    @if($complaint->repairDeadline())
                                        (s/d {{ $complaint->repairDeadline()->format('d/m H:i') }})
                                    @endif
                                </small>
                            @else
                                <small style="color:#c0392b;">Nomor WhatsApp PIC belum tersedia.</small>
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
                        @endif

                        {{-- Guru/Organisasi & pengunjung: tidak punya hak manipulasi --}}
                        @if(!$isAdmin && !$isPic)
                            <small style="color:#666;">Tidak ada hak manipulasi.</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Belum ada data pengaduan kerusakan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<script>
    document.querySelectorAll('select[name="status_repair"]').forEach(function (el) {
        el.dispatchEvent(new Event('change'));
    });
</script>
@endsection
