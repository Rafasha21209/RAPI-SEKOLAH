@extends('layouts.app')

@section('content')
@php
    $me       = Auth::user();
    $roleName = $me->role->role_name ?? '';
    $isAdmin  = $roleName === 'Waka/Admin';
    $canApply = $me->can('create', \App\Models\Event::class);
    $todayWib = now('Asia/Jakarta')->toDateString();
    $minBorrow = now('Asia/Jakarta')->addDay()->toDateString(); // H-1: paling cepat besok
    $minDate   = $isAdmin ? $todayWib : $minBorrow;             // Waka/Admin boleh hari ini (sesi yang belum lewat)
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
            sebelum tampil di <a href="{{ route('public.calendar') }}" target="_blank">kalender publik</a>.
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

    {{-- Form Pengajuan Agenda (Guru/Organisasi & Waka/Admin) --}}
    @if($canApply)
        <fieldset style="margin-bottom: 20px; padding: 15px;">
            <legend><strong>Form Pengajuan Agenda / Peminjaman Ruang</strong></legend>
            <form action="{{ route('events.store') }}" method="POST" enctype="multipart/form-data" data-session-guard>
                @csrf
                <p>
                    <label>Nama Agenda / Kegiatan:</label><br>
                    <input type="text" name="event_name" value="{{ old('event_name') }}" required style="width: 100%; max-width: 400px;">
                </p>
                <p>
                    <label>Pilih Ruangan:</label><br>
                    <select name="room_id" required>
                        <option value="">-- Pilih Ruangan --</option>
                        @foreach($rooms as $room)
                            <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>{{ $room->room_name }}</option>
                        @endforeach
                    </select>
                </p>
                <p>
                    <label>Tanggal:</label><br>
                    <input type="date" name="date" value="{{ old('date') }}" min="{{ $minDate }}" required>
                    <small style="display:block; color:#666;">
                        @if($isAdmin)
                            Waka/Admin boleh memilih hari ini untuk sesi yang belum dimulai.
                        @else
                            Peminjaman diajukan minimal H-1 (paling cepat {{ \Carbon\Carbon::parse($minBorrow)->format('d/m/Y') }}).
                        @endif
                    </small>
                </p>
                <p>
                    <label>Sesi Mulai:</label>
                    <select name="start_session" required>
                        <option value="">-- Pilih --</option>
                        @for($i = \App\Models\Event::SESSION_MIN; $i <= \App\Models\Event::SESSION_MAX; $i++)
                            <option value="{{ $i }}" {{ old('start_session') == $i ? 'selected' : '' }}>Sesi {{ $i }} ({{ \App\Models\Event::sessionTimeRange($i, $i) }})</option>
                        @endfor
                    </select>
                    <label>Sesi Selesai:</label>
                    <select name="end_session" required>
                        <option value="">-- Pilih --</option>
                        @for($i = \App\Models\Event::SESSION_MIN; $i <= \App\Models\Event::SESSION_MAX; $i++)
                            <option value="{{ $i }}" {{ old('end_session') == $i ? 'selected' : '' }}>Sesi {{ $i }} ({{ \App\Models\Event::sessionTimeRange($i, $i) }})</option>
                        @endfor
                    </select>
                    <small style="display:block; color:#666;">Pilih sesi yang sama jika hanya satu sesi.</small>
                    <small class="sg-note" style="display:block; color:#666;"></small>
                </p>
                <p>
                    <label>Unggah Foto Izin:</label><br>
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg" required>
                </p>
                <button type="submit">Ajukan Peminjaman</button>
                <small style="display:block; color:#666; margin-top:6px;">
                    Status awal otomatis <strong>Pending</strong> — menunggu persetujuan Waka/Admin.
                </small>
            </form>
        </fieldset>
    @endif

    <h3>Daftar Seluruh Agenda</h3>
    <table class="table-stack" border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th>Tanggal & Sesi</th>
                <th>Ruangan</th>
                <th>Agenda</th>
                <th>Peminjam</th>
                <th>Foto Izin</th>
                <th>Status / Alasan Pindah</th>
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
                @endphp
                <tr>
                    <td data-label="Tanggal &amp; Sesi">
                        {{ $event->date->format('d/m/Y') }}<br>
                        <small>{{ $event->sessionLabel() }}</small>
                    </td>
                    <td data-label="Ruangan">{{ $event->room->room_name ?? '-' }}</td>
                    <td data-label="Agenda">{{ $event->event_name }}</td>
                    <td data-label="Peminjam">{{ $event->user->fullname ?? $event->user->username ?? '-' }}</td>
                    <td data-label="Foto Izin">
                        @if($event->photo_path)
                            <a href="{{ asset('storage/' . $event->photo_path) }}" target="_blank">Lihat Foto</a>
                        @else
                            Tidak ada foto
                        @endif
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
                                        <option value="{{ $room->id }}">{{ $room->room_name }}</option>
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
                                    <input type="text" name="event_name" value="{{ $event->event_name }}" required style="font-size: 11px; width: 130px;"><br>
                                    <select name="room_id" required style="font-size: 11px; margin-top: 3px;">
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}" {{ $event->room_id == $room->id ? 'selected' : '' }}>{{ $room->room_name }}</option>
                                        @endforeach
                                    </select><br>
                                    <input type="date" name="date" value="{{ $event->date->format('Y-m-d') }}"{!! $event->date->format('Y-m-d') >= $todayWib ? ' min="' . $todayWib . '"' : '' !!} required style="font-size: 11px; margin-top: 3px;"><br>
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
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg" style="font-size: 11px; margin-top: 3px;"><br>
                                    <button type="submit" style="font-size: 11px; margin-top: 3px;">Simpan Perubahan</button>
                                </form>
                            </details>
                        @endif

                        @if(!$bisaApprove && !$bisaMove && !$bisaEdit && !$bisaLapor)
                            <span style="color: gray; font-size: 12px;">Read-Only</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">Belum ada data agenda tercatat.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@include('partials.session-guard')
@endsection
