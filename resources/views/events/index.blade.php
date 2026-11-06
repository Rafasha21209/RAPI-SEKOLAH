@extends('layouts.app')

@section('content')
@php
    $me       = Auth::user();
    $roleName = $me->role->role_name ?? '';
    $isAdmin  = $roleName === 'Waka/Admin';
    $isSiswa  = $roleName === 'Siswa';
    $canApply = $me->can('create', \App\Models\Event::class);
    $todayWib = now('Asia/Jakarta')->toDateString();
    $minBorrow = now('Asia/Jakarta')->addDay()->toDateString(); // H-1: paling cepat besok
    $minDate   = $isAdmin ? $todayWib : $minBorrow;             // Waka/Admin boleh hari ini (sesi yang belum lewat)
    $timeType  = old('time_type', 'session');
    $schoolOpen  = \App\Models\Event::minutesToHhmm(\App\Models\Event::schoolSpanMinutes('2026-01-05')[0]);   // Senin
    $schoolClose = \App\Models\Event::minutesToHhmm(\App\Models\Event::schoolSpanMinutes('2026-01-05')[1]);
    $typeMeta = \App\Models\Event::TYPE_META;
@endphp

<div style="padding: 20px;">
    <h2>Modul Agenda & Peminjaman Ruangan</h2>
    <div class="page-intro">
        <p>
            Login sebagai <strong>{{ $me->fullname ?? $me->username }}</strong>
            <span class="status-badge badge-info">{{ $roleName ?: '-' }}</span>
        </p>
        <p>
            Agenda yang baru diajukan berstatus <strong>Pending</strong> dan menunggu persetujuan Waka/Admin
            sebelum tampil di <a href="{{ route('public.calendar') }}">kalender publik</a>.
        </p>
    </div>

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

    @if($isAdmin)
        @include('partials.admin-nav', ['active' => 'events'])
    @endif

    {{-- Form Pengajuan (Guru/Organisasi, Siswa & Waka/Admin) --}}
    @if($canApply)
        <fieldset style="margin-bottom: 20px; padding: 15px;">
            <legend><strong>Form Pengajuan Peminjaman Ruangan</strong></legend>
            <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" data-session-guard id="form-pengajuan">
                @csrf
                <p>
                    <label for="event_name">Nama Pengajuan:</label><br>
                    <input type="text" id="event_name" name="event_name" value="{{ old('event_name') }}" required maxlength="255"
                           placeholder="mis. Rapat Persiapan Ujian Semester" style="width: 100%; max-width: 400px;">
                </p>
                <p>
                    <label for="responsible_name">Nama Penanggung Jawab Peminjaman:</label><br>
                    <input type="text" id="responsible_name" name="responsible_name" value="{{ old('responsible_name', $me->fullname ?? $me->username) }}" required maxlength="100"
                           placeholder="Nama orang yang bertanggung jawab atas peminjaman" style="width: 100%; max-width: 400px;">
                </p>
                <p>
                    <label for="category">Kategori Pengajuan:</label><br>
                    <select id="category" name="category" required>
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category', $isSiswa ? 'Kegiatan Siswa' : '') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                    @if($isSiswa)
                        <small style="display:block; color:#666;">Akun siswa hanya dapat mengajukan kategori Kegiatan Siswa.</small>
                    @endif
                </p>
                <p>
                    <label for="room_id">Ruangan yang Dipinjam:</label><br>
                    <select id="room_id" name="room_id" required>
                        <option value="">-- Pilih Ruangan --</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" data-name="{{ $room->room_name }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>{{ $room->optionLabel() }}</option>
                        @endforeach
                    </select>
                </p>
                <p>
                    <label for="date">Tanggal:</label><br>
                    <input type="date" id="date" name="date" value="{{ old('date') }}" min="{{ $minDate }}" required>
                    <small style="display:block; color:#666;">
                        @if($isAdmin)
                            Waka/Admin boleh memilih hari ini untuk sesi yang belum dimulai.
                        @else
                            Peminjaman diajukan minimal H-1 (paling cepat {{ \Carbon\Carbon::parse($minBorrow)->format('d/m/Y') }}).
                        @endif
                    </small>
                    <small id="off-day-note" style="display:none; color:#c0392b;"></small>
                </p>

                <p>
                    <label>Waktu Peminjaman:</label><br>
                    <label class="inline-choice"><input type="radio" name="time_type" value="session" {{ $timeType === 'outside' ? '' : 'checked' }}> Pada jam sekolah (pilih sesi)</label>
                    <label class="inline-choice"><input type="radio" name="time_type" value="outside" {{ $timeType === 'outside' ? 'checked' : '' }}> Di luar jam sekolah (isi jam)</label>
                    <small style="display:block; color:#666;">
                        Jam sekolah Senin-Kamis {{ $schoolOpen }}-{{ $schoolClose }}, Jumat sampai 13:50. Peminjaman di luar jam sekolah
                        (termasuk Sabtu, Minggu & hari libur nasional) <strong>hanya dapat diajukan oleh guru</strong>.
                    </small>
                </p>

                <p id="time-session">
                    <label for="start_session">Sesi Mulai:</label>
                    <select id="start_session" name="start_session" required>
                        <option value="">-- Pilih --</option>
                        @for($i = \App\Models\Event::SESSION_MIN; $i <= \App\Models\Event::SESSION_MAX; $i++)
                            <option value="{{ $i }}" {{ old('start_session') == $i ? 'selected' : '' }}>Sesi {{ $i }} ({{ \App\Models\Event::sessionTimeRange($i, $i) }})</option>
                        @endfor
                    </select>
                    <label for="end_session">Sesi Selesai:</label>
                    <select id="end_session" name="end_session" required>
                        <option value="">-- Pilih --</option>
                        @for($i = \App\Models\Event::SESSION_MIN; $i <= \App\Models\Event::SESSION_MAX; $i++)
                            <option value="{{ $i }}" {{ old('end_session') == $i ? 'selected' : '' }}>Sesi {{ $i }} ({{ \App\Models\Event::sessionTimeRange($i, $i) }})</option>
                        @endfor
                    </select>
                    <small style="display:block; color:#666;">Pilih sesi yang sama jika hanya satu sesi.</small>
                    <small class="sg-note" style="display:block; color:#666;"></small>
                </p>

                <p id="time-outside" style="display:none;">
                    <label for="start_time">Jam Mulai:</label>
                    <input type="time" id="start_time" name="start_time" value="{{ old('start_time') }}" min="05:00" max="22:00" disabled>
                    <label for="end_time">Jam Selesai:</label>
                    <input type="time" id="end_time" name="end_time" value="{{ old('end_time') }}" min="05:00" max="22:00" disabled>
                    <small style="display:block; color:#666;">Antara 05:00 dan 22:00 WIB. Pada hari sekolah, jam tidak boleh beririsan dengan jam pelajaran.</small>
                </p>

                <div id="siswa-warning" class="form-warning" style="display:none;" role="alert">
                    Peminjaman di luar jam sekolah (termasuk Sabtu, Minggu & hari libur) hanya dapat diajukan oleh guru.
                    Pilih hari sekolah dan sesi pada jam pelajaran.
                </div>

                {{-- INFO RUANGAN KOSONG: diperbarui otomatis sesuai tanggal & waktu yang dipilih --}}
                <div id="room-availability" class="availability" aria-live="polite" style="display:none;">
                    <strong class="availability-title"></strong>
                    <p class="availability-note"></p>
                    <div class="availability-chips"></div>
                </div>

                <p>
                    <label for="description">Tujuan Penggunaan:</label><br>
                    <textarea id="description" name="description" rows="3" minlength="10" maxlength="1000" required
                              placeholder="Jelaskan untuk apa ruangan digunakan (min. 10 karakter)"
                              style="width: 100%; max-width: 400px;">{{ old('description') }}</textarea>
                </p>
                <p>
                    <label>Foto / Dokumen Izin (JPG, PNG, atau PDF, maks 4 MB):</label><br>
                    @include('partials.photo-input', ['name' => 'photo', 'required' => true, 'accept' => 'image/*,application/pdf'])
                </p>
                <button type="submit" id="btn-ajukan">Ajukan Peminjaman</button>
                <small style="display:block; color:#666; margin-top:6px;">
                    Status awal otomatis <strong>Pending</strong> — menunggu persetujuan Waka/Admin.
                </small>
            </form>
        </fieldset>
    @endif

    <h3>Daftar Pengajuan & Agenda <small style="font-weight:400; color:#666;">(terbaru di atas)</small></h3>

    {{-- Filter: data lama tetap dapat dicari / dibuka lewat halaman berikutnya --}}
    <form method="GET" action="{{ route('events.index') }}" class="list-filter">
        <input type="search" name="q" value="{{ $keyword }}" placeholder="Cari nama pengajuan, pengaju, atau ruangan" aria-label="Cari">
        <select name="status" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="pending"  {{ $status === 'pending'  ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
        <select name="category" aria-label="Filter kategori">
            <option value="">Semua kategori</option>
            @foreach(\App\Models\Event::CATEGORIES as $cat)
                <option value="{{ $cat }}" {{ $category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
            <option value="Lainnya" {{ $category === 'Lainnya' ? 'selected' : '' }}>Peminjaman Ruangan (tanpa kategori)</option>
        </select>
        <button type="submit">Terapkan</button>
        @if($keyword !== '' || $status !== '' || $category !== '')
            <a href="{{ route('events.index') }}" class="list-filter-reset">Reset</a>
        @endif
    </form>

    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Pengajuan</th>
                <th>Pengaju</th>
                <th>Ruangan</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                @php
                    $bisaApprove = $me->can('approve', $event);
                    $bisaMove    = $me->can('move', $event);
                    $bisaEdit    = $me->can('update', $event);
                    $bisaLapor   = $me->can('reportDamage', $event);
                    $adaKelola   = $bisaApprove || $bisaMove || $bisaEdit || $bisaLapor;
                    $meta        = $event->typeMeta();
                    $timeBased   = $event->start_session === null && $event->start_time !== null;
                    $photoUrl    = $event->photoUrl();
                    $payload     = $event->detailPayload();
                @endphp
                <tr>
                    <td data-label="No">{{ $events->firstItem() + $loop->index }}</td>
                    <td data-label="Nama Pengajuan">
                        <strong class="cell-title">{{ $event->event_name }}</strong><br>
                        <span class="cat-badge" style="background: {{ $meta['color'] }};">{{ $event->category ?: $meta['label'] }}</span>
                        @if($event->is_outside_hours)
                            <span class="cat-badge" style="background:#6d4c41;">Luar jam sekolah</span>
                        @endif
                    </td>
                    <td data-label="Pengaju">{{ $event->user->fullname ?? $event->user->username ?? '-' }}</td>
                    <td data-label="Ruangan">{{ $event->room->room_name ?? '-' }}</td>
                    <td data-label="Tanggal">
                        {{ $event->date->format('d/m/Y') }}<br>
                        <small>{{ $event->sessionLabel() }}</small>
                    </td>
                    <td data-label="Status">
                        @if($event->isApproved())
                            <span style="color: green;">Approved</span>
                        @elseif($event->normalizedStatus() === 'rejected')
                            <span style="color: #c0392b;">Rejected</span>
                            @if($event->reject_reason)
                                <br><small style="color: #c0392b;">Alasan ditolak: {{ $event->reject_reason }}</small>
                            @endif
                        @else
                            <span style="color: #b8860b;">Pending</span>
                        @endif
                        @if($event->move_reason)
                            <br><small style="color: orange;">Dipindah: {{ $event->move_reason }}</small>
                        @endif
                    </td>
                    <td data-label="Aksi" class="td-actions">
                        <button type="button" class="btn-detail" data-detail="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">Detail</button>
                        @if($photoUrl)
                            <a href="{{ $photoUrl }}" class="btn-detail btn-link" data-lightbox="event-{{ $event->id }}" data-caption="Foto / dokumen izin — {{ $event->event_name }}">Foto Izin</a>
                        @endif

                        @if($adaKelola)
                        <details class="kelola">
                            <summary>Kelola</summary>

                            @if($bisaLapor)
                                <details style="margin-bottom: 5px;">
                                    <summary style="font-size: 11px; cursor: pointer; color:#c0392b;"><strong>Lapor Kerusakan</strong></summary>
                                    <form action="{{ route('complaints.store') }}" method="POST" enctype="multipart/form-data" style="margin-top: 5px;">
                                        @csrf
                                        <input type="hidden" name="event_id" value="{{ $event->id }}">
                                        <small style="display:block;">
                                            Ruangan: <strong>{{ $event->room->room_name ?? '-' }}</strong><br>
                                            Pelapor: <strong>{{ $me->fullname ?? $me->username }}</strong><br>
                                            PIC: <strong>{{ $event->room?->responsiblePic()?->fullname ?? 'belum ditentukan' }}</strong>
                                        </small>
                                        <textarea name="description" rows="2" minlength="10" required placeholder="Jelaskan kerusakan (min. 10 karakter)" style="font-size: 11px; width: 100%; max-width: 220px;"></textarea><br>
                                        @include('partials.photo-input', ['name' => 'photo', 'required' => true])
                                        <button type="submit" style="font-size: 11px;">Kirim Laporan</button>
                                    </form>
                                </details>
                            @endif

                            @if($bisaApprove)
                                <form action="{{ route('events.status', $event->id) }}" method="POST" style="margin-bottom: 5px;">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" style="font-size: 11px; background: #28a745; color: white; border: none; padding: 3px 6px;">Setujui</button>
                                </form>
                                <form action="{{ route('events.status', $event->id) }}" method="POST" style="margin-bottom: 5px;">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="status" value="rejected">
                                    <input type="text" name="reject_reason" placeholder="Alasan penolakan..." required minlength="5" maxlength="500" style="font-size: 11px; width: 150px;">
                                    <button type="submit" style="font-size: 11px; background: #dc3545; color: white; border: none; padding: 3px 6px;">Tolak</button>
                                </form>
                            @endif

                            @if($bisaMove)
                                <form action="{{ route('events.move', $event->id) }}" method="POST" style="margin-top: 5px;">
                                    @csrf
                                    @method('PUT')
                                    <select name="new_room_id" required style="font-size: 11px;">
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}">{{ $room->optionLabel() }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="move_reason" placeholder="Alasan pemindahan..." required style="font-size: 11px; width: 130px;">
                                    <button type="submit" style="font-size: 11px;">Veto / Pindah</button>
                                </form>
                            @endif

                            @if($bisaEdit)
                                <details style="margin-top: 5px;">
                                    <summary style="font-size: 11px; cursor: pointer;">Edit Agenda</summary>
                                    <form action="{{ route('events.update', $event->id) }}" method="POST" enctype="multipart/form-data" style="margin-top: 5px;" data-session-guard>
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="event_name" value="{{ $event->event_name }}" required maxlength="255" placeholder="Nama pengajuan" style="font-size: 11px; width: 160px;"><br>
                                        <select name="category" style="font-size: 11px; margin-top: 3px;">
                                            <option value="">(tanpa kategori)</option>
                                            @foreach(\App\Models\Event::CATEGORIES as $cat)
                                                <option value="{{ $cat }}" {{ $event->category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                            @endforeach
                                        </select><br>
                                        <select name="room_id" required style="font-size: 11px; margin-top: 3px;">
                                            @foreach($rooms as $room)
                                                <option value="{{ $room->id }}" {{ $event->room_id == $room->id ? 'selected' : '' }}>{{ $room->optionLabel() }}</option>
                                            @endforeach
                                        </select><br>
                                        <input type="date" name="date" value="{{ $event->date->format('Y-m-d') }}"{!! $event->date->format('Y-m-d') >= $todayWib ? ' min="' . $todayWib . '"' : '' !!} required style="font-size: 11px; margin-top: 3px;"><br>
                                        @if($timeBased)
                                            <input type="time" name="start_time" value="{{ substr((string) $event->start_time, 0, 5) }}" min="05:00" max="22:00" required style="font-size: 11px; margin-top: 3px;">
                                            <input type="time" name="end_time" value="{{ substr((string) $event->end_time, 0, 5) }}" min="05:00" max="22:00" required style="font-size: 11px;"><br>
                                        @else
                                            <select name="start_session" required style="font-size: 11px; margin-top: 3px;">
                                                <option value="">Sesi mulai</option>
                                                @for($i = \App\Models\Event::SESSION_MIN; $i <= \App\Models\Event::SESSION_MAX; $i++)
                                                    <option value="{{ $i }}" {{ $event->start_session === $i ? 'selected' : '' }}>Sesi {{ $i }} ({{ \App\Models\Event::sessionTimeRange($i, $i, $event->date->format('Y-m-d')) }})</option>
                                                @endfor
                                            </select>
                                            <select name="end_session" required style="font-size: 11px;">
                                                <option value="">Sesi selesai</option>
                                                @for($i = \App\Models\Event::SESSION_MIN; $i <= \App\Models\Event::SESSION_MAX; $i++)
                                                    <option value="{{ $i }}" {{ $event->end_session === $i ? 'selected' : '' }}>Sesi {{ $i }} ({{ \App\Models\Event::sessionTimeRange($i, $i, $event->date->format('Y-m-d')) }})</option>
                                                @endfor
                                            </select><br>
                                        @endif
                                        <textarea name="description" rows="2" maxlength="1000" placeholder="Tujuan penggunaan" style="font-size: 11px; width: 100%; max-width: 220px; margin-top: 3px;">{{ $event->description }}</textarea><br>
                                        <input type="text" name="responsible_name" value="{{ $event->responsible_name }}" maxlength="100" placeholder="Nama penanggung jawab" style="font-size: 11px; width: 160px;"><br>
                                        <input type="file" name="photo" accept="image/jpeg,image/png,application/pdf" style="font-size: 11px; margin-top: 3px;"><br>
                                        <button type="submit" style="font-size: 11px; margin-top: 3px;">Simpan Perubahan</button>
                                    </form>
                                </details>
                            @endif
                        </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">
                        @if($keyword !== '' || $status !== '' || $category !== '')
                            Tidak ada pengajuan yang cocok dengan filter.
                        @else
                            Belum ada data agenda tercatat.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $events->links() }}
</div>

@include('partials.detail-modal')
@include('partials.session-guard')

@if($canApply)
<script>
    (function () {
        var form = document.getElementById('form-pengajuan');
        if (!form) { return; }

        var IS_SISWA   = {{ $isSiswa ? 'true' : 'false' }};
        var HOLIDAYS   = @json(array_keys(config('holidays', [])));
        var URL_AVAIL  = @json(route('events.availability'));

        var dateEl   = form.querySelector('[name="date"]');
        var roomEl   = form.querySelector('[name="room_id"]');
        var startS   = form.querySelector('[name="start_session"]');
        var endS     = form.querySelector('[name="end_session"]');
        var startT   = form.querySelector('[name="start_time"]');
        var endT     = form.querySelector('[name="end_time"]');
        var radios   = form.querySelectorAll('[name="time_type"]');
        var boxSes   = document.getElementById('time-session');
        var boxOut   = document.getElementById('time-outside');
        var offNote  = document.getElementById('off-day-note');
        var warn     = document.getElementById('siswa-warning');
        var submit   = document.getElementById('btn-ajukan');
        var panel    = document.getElementById('room-availability');
        var chips    = panel.querySelector('.availability-chips');
        var title    = panel.querySelector('.availability-title');
        var note     = panel.querySelector('.availability-note');
        var lastData = null, timer = null;

        function mode() {
            var m = 'session';
            radios.forEach(function (r) { if (r.checked) { m = r.value; } });
            return m;
        }

        function offDay(dateStr) {
            if (!dateStr) { return false; }
            var d = new Date(dateStr + 'T00:00:00');
            var dow = d.getDay();
            return dow === 0 || dow === 6 || HOLIDAYS.indexOf(dateStr) !== -1;
        }

        function applyMode() {
            var outside = mode() === 'outside';
            boxSes.style.display = outside ? 'none' : '';
            boxOut.style.display = outside ? '' : 'none';
            [startS, endS].forEach(function (el) { el.disabled = outside; el.required = !outside; });
            [startT, endT].forEach(function (el) { el.disabled = !outside; el.required = outside; });

            var off = offDay(dateEl.value);
            offNote.style.display = off ? 'block' : 'none';
            offNote.textContent = off ? 'Tanggal ini bukan hari sekolah (Sabtu/Minggu/libur nasional): dihitung sebagai peminjaman di luar jam sekolah.' : '';

            var blocked = IS_SISWA && (outside || off);
            warn.style.display = blocked ? 'block' : 'none';
            submit.disabled = blocked;
            scheduleCheck();
        }

        function render() {
            var d = lastData;
            if (!d || !d.ready) { panel.style.display = 'none'; return; }
            panel.style.display = 'block';
            chips.textContent = '';

            var free = d.rooms.filter(function (r) { return r.free; });
            title.textContent = free.length
                ? free.length + ' ruangan kosong dan dapat dipinjam pada ' + d.range + ' WIB'
                : 'Tidak ada ruangan kosong pada ' + d.range + ' WIB';
            note.textContent = '';

            var sel = roomEl.value ? d.rooms.filter(function (r) { return String(r.id) === roomEl.value; })[0] : null;
            if (sel && !sel.free) {
                note.textContent = 'Perhatian: ' + sel.name + ' sudah dipesan' + (sel.busy_by ? ' untuk "' + sel.busy_by + '"' : '') + ' pada waktu tersebut. Pilih ruangan lain di bawah ini.';
                note.className = 'availability-note is-busy';
            } else {
                note.textContent = free.length ? 'Ketuk nama ruangan untuk memilihnya.' : 'Coba ubah tanggal atau waktu peminjaman.';
                note.className = 'availability-note';
            }

            free.forEach(function (r) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'availability-chip';
                b.textContent = r.name;
                b.title = r.info || r.type || '';
                if (r.info) { var sm = document.createElement('small'); sm.textContent = ' - ' + r.info; sm.style.fontWeight = '400'; b.appendChild(sm); }
                b.addEventListener('click', function () { roomEl.value = String(r.id); render(); });
                chips.appendChild(b);
            });
        }

        function check() {
            var q = new URLSearchParams();
            if (!dateEl.value) { panel.style.display = 'none'; return; }
            q.set('date', dateEl.value);

            if (mode() === 'outside') {
                if (!startT.value || !endT.value || endT.value <= startT.value) { panel.style.display = 'none'; return; }
                q.set('start_time', startT.value);
                q.set('end_time', endT.value);
            } else {
                if (!startS.value) { panel.style.display = 'none'; return; }
                q.set('start_session', startS.value);
                q.set('end_session', endS.value || startS.value);
            }

            fetch(URL_AVAIL + '?' + q.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) { lastData = d; render(); })
                .catch(function () { panel.style.display = 'none'; });
        }

        function scheduleCheck() {
            clearTimeout(timer);
            timer = setTimeout(check, 250);
        }

        radios.forEach(function (r) { r.addEventListener('change', applyMode); });
        [dateEl, startS, endS, startT, endT].forEach(function (el) {
            el.addEventListener('change', function () { applyMode(); });
        });
        roomEl.addEventListener('change', render);
        applyMode();
    })();
</script>
@endif
@endsection
