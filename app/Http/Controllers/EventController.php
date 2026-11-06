<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Support\Holidays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * MODUL AGENDA / PEMINJAMAN RUANGAN (RAPI-Sekolah)
 *
 * Lihat dokumentasi alur & aturan di app/Models/Event.php dan
 * app/Policies/EventPolicy.php. Seluruh pengecekan role dilakukan di
 * BACKEND (authorize() via EventPolicy), bukan hanya menyembunyikan
 * tombol di frontend.
 */
class EventController extends Controller
{
    /**
     * Tanggal "hari ini" menurut waktu Indonesia (WIB), format Y-m-d.
     *
     * Dihitung eksplisit dengan Asia/Jakarta karena config/app.php memakai
     * timezone UTC. Tanpa ini, pukul 00:00-07:00 WIB server masih mengira
     * "hari ini" adalah kemarin, sehingga tanggal kemarin lolos validasi.
     */
    private function todayWib(): string
    {
        return Carbon::today('Asia/Jakarta')->toDateString();
    }

    /**
     * Tanggal paling awal yang boleh dipilih: Waka/Admin mulai hari ini,
     * Guru/Organisasi mulai besok (H-1).
     */
    private function minDateFor($user): string
    {
        return $user && $user->isAdmin() ? $this->todayWib() : $this->minBorrowDate();
    }

    /** Pesan error bila sesi melebihi jadwal hari tsb (Jumat hanya sampai sesi 8). */
    private function overSessionMessage(string $date, int $startSession, int $endSession): ?string
    {
        $max = Event::maxSessionFor($date);

        if ($startSession > $max || $endSession > $max) {
            return 'Pada tanggal tersebut jam pelajaran hanya sampai sesi ' . $max . '. Pilih sesi 1-' . $max . '.';
        }

        return null;
    }

    /** Pesan error bila sesi pada tanggal tsb sudah lewat jamnya; null bila masih boleh. */
    private function pastSessionMessage(string $date, int $session): ?string
    {
        if (!Event::isSessionPast($date, $session)) {
            return null;
        }

        return 'Sesi ' . $session . ' (' . Event::sessionTimeRange($session, $session, $date) . ' WIB) sudah lewat. '
            . 'Pilih sesi yang belum dimulai, atau pilih tanggal besok.';
    }

    /**
     * Tanggal paling awal yang boleh dipinjam = besok (H-1): pengajuan harus
     * dilakukan minimal satu hari sebelum tanggal pemakaian.
     */
    private function minBorrowDate(): string
    {
        return Carbon::tomorrow('Asia/Jakarta')->toDateString();
    }

    /** Batas waktu pinjam bebas (di luar jam sekolah): 05:00 - 22:00 WIB. */
    private const OUTSIDE_MIN = 5 * 60;
    private const OUTSIDE_MAX = 22 * 60;

    /**
     * Hanya kolom yang benar-benar ada di tabel events yang disimpan.
     * Mencegah error 500 bila migrasi terbaru belum dijalankan (php artisan migrate).
     */
    private function existingColumns(array $data): array
    {
        return array_intersect_key($data, array_flip(Schema::getColumnListing('events')));
    }

    /** Kategori yang boleh dipilih tiap role. Siswa hanya boleh "Kegiatan Siswa". */
    private function allowedCategories($user): array
    {
        return $user && $user->isSiswa() ? ['Kegiatan Siswa'] : Event::CATEGORIES;
    }

    /**
     * Pesan error bila waktu bebas (di luar jam sekolah) tidak valid; null bila valid.
     * Pada hari sekolah, waktu tidak boleh beririsan dengan jam pelajaran (gunakan pilihan sesi).
     */
    private function outsideTimeMessage(string $date, int $startMin, int $endMin): ?string
    {
        if ($startMin < self::OUTSIDE_MIN || $endMin > self::OUTSIDE_MAX) {
            return 'Waktu peminjaman di luar jam sekolah hanya boleh antara '
                . Event::minutesToHhmm(self::OUTSIDE_MIN) . ' dan ' . Event::minutesToHhmm(self::OUTSIDE_MAX) . ' WIB.';
        }

        if (Holidays::isSchoolDay($date)) {
            [$open, $close] = Event::schoolSpanMinutes($date);
            if ($startMin < $close && $endMin > $open) {
                return 'Waktu tersebut masih termasuk jam sekolah (' . Event::minutesToHhmm($open) . '-' . Event::minutesToHhmm($close)
                    . ' WIB). Pilih jam sebelum ' . Event::minutesToHhmm($open) . ' atau setelah ' . Event::minutesToHhmm($close)
                    . ', atau gunakan pilihan sesi.';
            }
        }

        return null;
    }

    /**
     * Menampilkan daftar seluruh agenda (dashboard internal, wajib login).
     * Terbaru lebih dulu (tanggal turun), 15 baris per halaman; data lama tetap dapat
     * diakses lewat pagination, pencarian, dan filter status/kategori.
     */
    public function index(Request $request)
    {
        $status   = (string) $request->query('status', '');
        $category = (string) $request->query('category', '');
        $keyword  = trim((string) $request->query('q', ''));

        $events = Event::with(['room.pic', 'user'])
            ->when($status === 'approved', fn ($q) => $q->whereRaw('LOWER(status) = ?', ['approved']))
            ->when($status === 'rejected', fn ($q) => $q->whereRaw('LOWER(status) = ?', ['rejected']))
            ->when($status === 'pending', fn ($q) => $q->whereRaw('LOWER(status) NOT IN (?, ?)', ['approved', 'rejected']))
            ->when(in_array($category, Event::CATEGORIES, true), fn ($q) => $q->where('category', $category))
            ->when($category === 'Lainnya', fn ($q) => $q->whereNull('category'))
            ->when($keyword !== '', function ($q) use ($keyword) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
                $q->where(function ($w) use ($like) {
                    $w->where('event_name', 'like', $like)
                      ->orWhereHas('user', fn ($u) => $u->where('fullname', 'like', $like)->orWhere('username', 'like', $like))
                      ->orWhereHas('room', fn ($r) => $r->where('room_name', 'like', $like));
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('start_session')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $rooms = Room::where('is_active', true)->orderBy('id')->get();

        $categories = $this->allowedCategories(Auth::user());

        return view('events.index', compact('events', 'rooms', 'categories', 'status', 'category', 'keyword'));
    }

    /**
     * INFO RUANGAN KOSONG: ruangan aktif mana saja yang masih kosong (tidak dipesan/disetujui)
     * pada tanggal + sesi atau jam tertentu. Dipakai form pengajuan untuk menampilkan
     * pemberitahuan "ruangan kosong yang dapat dipinjam".
     */
    public function availability(Request $request)
    {
        $v = $request->validate([
            'date'          => ['required', 'date_format:Y-m-d'],
            'start_session' => ['nullable', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX],
            'end_session'   => ['nullable', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX],
            'start_time'    => ['nullable', 'date_format:H:i'],
            'end_time'      => ['nullable', 'date_format:H:i'],
        ]);

        $date = $v['date'];

        if (!empty($v['start_time']) && !empty($v['end_time'])) {
            $range = [Event::hhmmToMinutes($v['start_time']), Event::hhmmToMinutes($v['end_time'])];
        } elseif (!empty($v['start_session'])) {
            $range = Event::sessionRangeMinutes((int) $v['start_session'], (int) ($v['end_session'] ?? $v['start_session']), $date);
        } else {
            $range = null;
        }

        if ($range === null || $range[1] <= $range[0]) {
            return response()->json(['ready' => false, 'rooms' => []]);
        }

        $booked = Event::where('date', $date)
            ->where('status', '!=', Event::STATUS_REJECTED)
            ->get()
            ->filter(function (Event $e) use ($range) {
                $r = $e->rangeMinutes();

                return $r !== null && $r[0] < $range[1] && $r[1] > $range[0];
            })
            ->keyBy('room_id');

        $rooms = Room::where('is_active', true)->orderBy('room_name')->get()->map(function (Room $room) use ($booked) {
            $busy = $booked->get($room->id);

            return [
                'id'       => $room->id,
                'name'     => $room->room_name,
                'type'     => $room->room_type,
                'capacity' => $room->capacity,
                'info'     => $room->infoText(),
                'free'     => $busy === null,
                'busy_by'  => $busy?->event_name,
            ];
        })->values();

        return response()->json([
            'ready'      => true,
            'range'      => Event::minutesToHhmm($range[0]) . '-' . Event::minutesToHhmm($range[1]),
            'school_day' => Holidays::isSchoolDay($date),
            'off_reason' => Holidays::offReason($date),
            'free_count' => $rooms->where('free', true)->count(),
            'rooms'      => $rooms,
        ]);
    }

    /**
     * KETENTUAN: Endpoint JSON untuk FullCalendar.
     * HANYA agenda berstatus "approved" yang boleh ditarik ke sini —
     * agenda yang belum dikonfirmasi Admin/Waka TIDAK BOLEH muncul di
     * kalender publik. Route ini PUBLIK (tanpa login), lihat routes/web.php.
     *
     * Ditambah hari libur nasional/cuti bersama dari config/holidays.php.
     * Nama peminjam & PIC hanya dikirim bila pengunjung sudah login.
     */
    public function calendarFeed(Request $request)
    {
        $loggedIn = Auth::check();

        $events = Event::approved()
            ->with(['room.pic', 'user'])
            ->get()
            ->map(function (Event $event) use ($loggedIn) {
                $tanggal = $event->date->format('Y-m-d');
                $meta    = $event->typeMeta();
                $pic     = $event->room?->responsiblePic();

                $item = [
                    'id'        => $event->id,
                    'title'     => $event->event_name,
                    'color'     => $meta['color'],
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type'        => $event->typeKey(),
                        'typeLabel'   => $meta['label'],
                        'color'       => $meta['color'],
                        'name'        => $event->event_name,
                        'room'        => $event->room->room_name ?? '-',
                        'location'    => $event->room->location ?? null,
                        'session'     => $event->sessionLabel(),
                        'dateLabel'   => $event->date->locale('id')->translatedFormat('l, d F Y'),
                        'description' => $event->description,
                        'notes'       => $event->notes,
                        'responsible' => $event->responsible_name,
                        'outside'     => (bool) $event->is_outside_hours,
                        'requester'   => $loggedIn ? ($event->user->fullname ?? $event->user->username ?? null) : null,
                        'pic'         => $loggedIn ? ($pic->fullname ?? null) : null,
                        'picNote'     => $loggedIn ? ($pic->pic_note ?? null) : null,
                    ],
                ];

                if ($event->start_session) {
                    // Waktu pinjam berbasis sesi -> tampil sebagai agenda sehari penuh.
                    $item['start']  = $tanggal;
                    $item['allDay'] = true;
                } else {
                    // Waktu bebas (di luar jam sekolah) / data lama berbasis jam.
                    $item['start'] = $tanggal . 'T' . substr((string) $event->start_time, 0, 8);
                    $item['end']   = $tanggal . 'T' . substr((string) $event->end_time, 0, 8);
                }

                return $item;
            })
            ->values();

        // Hari libur nasional / cuti bersama (sebatas rentang yang diminta kalender).
        $from = substr((string) $request->query('start', ''), 0, 10);
        $to   = substr((string) $request->query('end', ''), 0, 10);
        $meta = Event::TYPE_META['libur_nasional'];

        $holidays = collect(Holidays::all())
            ->filter(fn ($h, $date) => (!$from || $date >= $from) && (!$to || $date < $to))
            ->map(function ($h, $date) use ($meta) {
                $cuti = $h[1] === 'cuti';

                return [
                    'id'        => 'holiday-' . $date,
                    'title'     => $h[0],
                    'start'     => $date,
                    'allDay'    => true,
                    'color'     => $meta['color'],
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type'        => 'libur_nasional',
                        'typeLabel'   => $cuti ? 'Cuti Bersama' : $meta['label'],
                        'color'       => $meta['color'],
                        'name'        => $h[0],
                        'dateLabel'   => \Carbon\Carbon::parse($date)->locale('id')->translatedFormat('l, d F Y'),
                        'description' => $cuti
                            ? 'Cuti bersama sesuai SKB 3 Menteri. Tidak ada kegiatan belajar mengajar.'
                            : 'Hari libur nasional sesuai SKB 3 Menteri. Tidak ada kegiatan belajar mengajar.',
                    ],
                ];
            })
            ->values();

        return response()->json($events->concat($holidays)->values());
    }

    /**
     * KETENTUAN: Guru/Siswa dapat mengajukan agenda, status awal WAJIB "pending"
     * (tidak bisa dipilih dari input) dan wajib menunggu approval Admin/Waka.
     *
     * PEMINJAMAN DI LUAR JAM SEKOLAH (time_type=outside, atau tanggal Sabtu/Minggu/libur nasional):
     * hanya Guru (dan Waka/Admin). Siswa ditolak di BACKEND, bukan sekadar disembunyikan di tampilan.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $user    = Auth::user();
        $outside = $request->input('time_type') === 'outside';

        $rules = [
            'room_id'     => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'event_name'  => ['required', 'string', 'max:255'],
            'category'    => ['required', 'string', Rule::in($this->allowedCategories($user))],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'responsible_name' => ['required', 'string', 'max:100'],
            'date'        => ['required', 'date_format:Y-m-d', 'after_or_equal:' . $this->minDateFor($user)],
            'time_type'   => ['nullable', Rule::in(['session', 'outside'])],
            'photo'       => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
        ];

        if ($outside) {
            $rules['start_time'] = ['required', 'date_format:H:i'];
            $rules['end_time']   = ['required', 'date_format:H:i', 'after:start_time'];
        } else {
            $rules['start_session'] = ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX];
            $rules['end_session']   = ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX, 'gte:start_session'];
        }

        $validated = $request->validate($rules, [
            'date.after_or_equal' => $user->isAdmin()
                ? 'Tanggal peminjaman tidak boleh sebelum hari ini.'
                : 'Peminjaman harus diajukan minimal H-1 (paling cepat untuk tanggal ' . Carbon::tomorrow('Asia/Jakarta')->format('d/m/Y') . ').',
            'event_name.required'    => 'Nama pengajuan wajib diisi.',
            'category.required'      => 'Kategori pengajuan wajib dipilih.',
            'category.in'            => $user->isSiswa()
                ? 'Siswa hanya dapat mengajukan kategori "Kegiatan Siswa".'
                : 'Kategori tidak valid. Pilih: ' . implode(', ', Event::CATEGORIES) . '.',
            'description.required'   => 'Tujuan penggunaan ruangan wajib diisi (min. 10 karakter).',
            'description.min'        => 'Tujuan penggunaan minimal 10 karakter.',
            'description.max'        => 'Tujuan penggunaan maksimal 1000 karakter.',
            'responsible_name.required' => 'Nama penanggung jawab peminjaman wajib diisi.',
            'responsible_name.max'   => 'Nama penanggung jawab maksimal 100 karakter.',
            'start_session.required' => 'Sesi mulai wajib dipilih.',
            'end_session.required'   => 'Sesi selesai wajib dipilih.',
            'start_session.between'  => 'Sesi mulai harus antara 1 sampai 10.',
            'end_session.between'    => 'Sesi selesai harus antara 1 sampai 10.',
            'end_session.gte'        => 'Sesi selesai tidak boleh lebih kecil dari sesi mulai.',
            'start_time.required'    => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Format jam mulai harus HH:MM.',
            'end_time.required'      => 'Jam selesai wajib diisi.',
            'end_time.date_format'   => 'Format jam selesai harus HH:MM.',
            'end_time.after'         => 'Jam selesai harus lebih besar dari jam mulai.',
            'photo.required'         => 'Foto / dokumen izin peminjaman wajib diunggah.',
            'photo.mimes'            => 'Berkas izin harus berformat JPG, PNG, atau PDF.',
            'photo.max'              => 'Ukuran berkas izin maksimal 4 MB.',
        ]);

        $date = $validated['date'];

        // Di luar jam sekolah = waktu bebas, ATAU tanggal yang bukan hari sekolah (Sabtu/Minggu/libur nasional).
        $isOutsideHours = $outside || !Holidays::isSchoolDay($date);

        // ATURAN ROLE (backend): hanya Guru (dan Waka/Admin) yang boleh meminjam di luar jam sekolah.
        if ($isOutsideHours && !$user->isAdmin() && !$user->isGuru()) {
            $alasan = !$outside ? ' (' . Holidays::offReason($date) . ' bukan hari sekolah)' : '';

            return back()->withInput()->with(
                'error',
                'Peminjaman ruangan di luar jam sekolah' . $alasan . ' hanya dapat diajukan oleh guru. '
                . 'Akun siswa hanya dapat meminjam pada jam sekolah (hari sekolah, sesi 1-' . Event::maxSessionFor($date) . ').'
            );
        }

        $data = [];

        if ($outside) {
            $startMin = Event::hhmmToMinutes($validated['start_time']);
            $endMin   = Event::hhmmToMinutes($validated['end_time']);

            if ($pesan = $this->outsideTimeMessage($date, $startMin, $endMin)) {
                return back()->withErrors(['start_time' => $pesan])->withInput();
            }

            if ($date === $this->todayWib() && $startMin <= Carbon::now('Asia/Jakarta')->hour * 60 + Carbon::now('Asia/Jakarta')->minute) {
                return back()->withErrors(['start_time' => 'Jam mulai sudah lewat. Pilih jam yang belum dimulai.'])->withInput();
            }

            $data = [
                'start_time'    => $validated['start_time'],
                'end_time'      => $validated['end_time'],
                'start_session' => null,
                'end_session'   => null,
            ];
        } else {
            $startSession = (int) $validated['start_session'];
            $endSession   = (int) $validated['end_session'];

            if ($pesan = $this->overSessionMessage($date, $startSession, $endSession)) {
                return back()->withErrors(['end_session' => $pesan])->withInput();
            }

            // Sesi yang jamnya sudah lewat (menurut jam WIB sekarang) tidak boleh dipilih untuk hari ini.
            if ($pesan = $this->pastSessionMessage($date, $startSession)) {
                return back()->withErrors(['start_session' => $pesan])->withInput();
            }

            $range    = Event::sessionRangeMinutes($startSession, $endSession, $date);
            $startMin = $range[0];
            $endMin   = $range[1];

            $data = ['start_session' => $startSession, 'end_session' => $endSession];
        }

        // KETENTUAN: validasi bentrok jadwal mempertimbangkan agenda APPROVED maupun PENDING,
        // baik yang berbasis sesi maupun waktu bebas (lihat Event::overlappingByTime).
        $bentrok = Event::overlappingByTime((int) $validated['room_id'], $date, $startMin, $endMin)->isNotEmpty()
            || (!$outside && Event::conflicting((int) $validated['room_id'], $date, $data['start_session'], $data['end_session'])->exists());

        if ($bentrok) {
            return back()->withInput()->with(
                'error',
                'Gagal mengajukan! Ruangan sudah dipesan (baik yang sudah disetujui maupun yang masih menunggu approval) pada rentang waktu tersebut.'
            );
        }

        $photoPath = $request->file('photo')->store('events', 'public');

        $event = Event::create($this->existingColumns($data + [
            'room_id'          => $validated['room_id'],
            'user_id'          => Auth::id(),
            'event_name'       => trim($validated['event_name']),
            'category'         => $validated['category'],
            'description'      => trim($validated['description']),
            'responsible_name' => trim($validated['responsible_name']),
            'is_outside_hours' => $isOutsideHours,
            'date'             => $date,
            'status'           => Event::STATUS_PENDING, // WAJIB pending, bukan dari input user
            'photo_path'       => $photoPath,
        ]));

        // Notifikasi ke peminjam: pengajuan berhasil dikirim.
        Notification::create([
            'user_id' => Auth::id(),
            'title'   => 'Pengajuan Peminjaman Terkirim',
            'message' => 'Pengajuan "' . $event->event_name . '" (' . $event->date->format('d/m/Y')
                . ', ' . $event->sessionLabel() . ') berhasil dikirim dan menunggu persetujuan Waka/Admin.',
        ]);

        return redirect()->back()->with(
            'success',
            'Peminjaman ruangan berhasil diajukan dan menunggu persetujuan Admin/Waka.'
        );
    }

    /**
     * Menyetujui atau menolak pengajuan (khusus Waka/Admin).
     */
    public function updateApproval(Request $request, $id)
    {
        $event = Event::with('room')->findOrFail($id);
        $this->authorize('approve', $event);

        $validated = $request->validate([
            'status'        => ['required', Rule::in([Event::STATUS_APPROVED, Event::STATUS_REJECTED])],
            // Alasan wajib diisi ketika pengajuan ditolak.
            'reject_reason' => [
                Rule::requiredIf(fn () => $request->input('status') === Event::STATUS_REJECTED),
                'nullable', 'string', 'min:5', 'max:500',
            ],
        ], [
            'reject_reason.required' => 'Alasan penolakan wajib diisi.',
            'reject_reason.min'      => 'Alasan penolakan minimal 5 karakter.',
            'reject_reason.max'      => 'Alasan penolakan maksimal 500 karakter.',
        ]);

        // Cek ulang bentrok terhadap agenda LAIN yang sudah approved, untuk
        // berjaga-jaga kalau ada dua pengajuan pending yang sama-sama
        // menabrak jadwal dan admin mencoba menyetujui keduanya.
        if ($validated['status'] === Event::STATUS_APPROVED && ($range = $event->rangeMinutes())) {
            $bentrok = Event::overlappingByTime(
                $event->room_id,
                $event->date->format('Y-m-d'),
                $range[0],
                $range[1],
                $event->id
            )->contains(fn (Event $e) => $e->isApproved());

            if ($bentrok) {
                return back()->with(
                    'error',
                    'Tidak bisa menyetujui: ruangan sudah terisi agenda lain yang sudah disetujui pada waktu yang sama.'
                );
            }
        }

        $ditolak = $validated['status'] === Event::STATUS_REJECTED;

        $event->update([
            'status'        => $validated['status'],
            'approved_by'   => Auth::id(),
            'reject_reason' => $ditolak ? trim($validated['reject_reason']) : null,
        ]);

        if ($event->user_id) {
            Notification::create([
                'user_id' => $event->user_id,
                'title'   => $ditolak ? 'Peminjaman Ruangan Ditolak' : 'Peminjaman Ruangan Disetujui',
                'message' => $ditolak
                    ? 'Pengajuan agenda "' . $event->event_name . '" ditolak. Alasan: ' . trim($validated['reject_reason'])
                    : 'Pengajuan agenda "' . $event->event_name . '" (' . $event->date->format('d/m/Y')
                        . ', ' . $event->sessionLabel() . ') DISETUJUI. Ruangan: ' . ($event->room->room_name ?? '-') . '.',
            ]);
        }

        return back()->with('success', 'Status peminjaman diperbarui menjadi "' . ucfirst($validated['status']) . '".');
    }

    /**
     * Hak veto Waka/Admin: memindahkan agenda ke ruangan lain.
     */
    public function moveRoom(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $this->authorize('move', $event);

        $validated = $request->validate([
            'new_room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'move_reason' => ['required', 'string', 'min:5'],
        ]);

        $range   = $event->rangeMinutes();
        $bentrok = $range !== null && Event::overlappingByTime(
            (int) $validated['new_room_id'],
            $event->date->format('Y-m-d'),
            $range[0],
            $range[1],
            $event->id
        )->isNotEmpty();

        if ($bentrok) {
            return back()->with('error', 'Gagal memindahkan! Ruangan tujuan sudah terisi pada waktu tersebut.');
        }

        $event->update([
            'room_id'     => $validated['new_room_id'],
            'move_reason' => $validated['move_reason'],
            'approved_by' => Auth::id(),
        ]);

        if ($event->user_id) {
            Notification::create([
                'user_id' => $event->user_id,
                'title'   => 'Ruangan Agenda Dipindahkan',
                'message' => 'Agenda "' . $event->event_name . '" dipindahkan ke ruangan baru. Alasan: ' . $validated['move_reason'],
            ]);
        }

        return back()->with('success', 'Ruangan berhasil dipindahkan dan notifikasi telah dikirim ke peminjam.');
    }

    /**
     * Mengubah detail agenda secara penuh (khusus Waka/Admin).
     */
    public function update(Request $request, $id)
    {
        $event = Event::findOrFail($id);
        $this->authorize('update', $event);

        // Agenda berbasis jam bebas (di luar jam sekolah) tetap diedit dengan jam, bukan sesi.
        $timeBased = $event->start_session === null && $event->start_time !== null;

        // Tanggal baru tidak boleh di masa lalu. Kalau tanggalnya tidak diubah
        // (admin hanya mengganti nama/jam/ruangan agenda lama), tidak dicek.
        $dateRules = ['required', 'date_format:Y-m-d'];
        if ($request->input('date') !== $event->date->format('Y-m-d')) {
            $dateRules[] = 'after_or_equal:' . $this->todayWib();
        }

        $rules = [
            'room_id'     => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'event_name'  => ['required', 'string', 'max:255'],
            'category'    => ['nullable', Rule::in(Event::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'responsible_name' => ['nullable', 'string', 'max:100'],
            'date'        => $dateRules,
            'photo'       => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:4096'],
        ];

        if ($timeBased) {
            $rules['start_time'] = ['required', 'date_format:H:i'];
            $rules['end_time']   = ['required', 'date_format:H:i', 'after:start_time'];
        } else {
            $rules['start_session'] = ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX];
            $rules['end_session']   = ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX, 'gte:start_session'];
        }

        $validated = $request->validate($rules, [
            'date.after_or_equal' => 'Tanggal baru tidak boleh sebelum hari ini.',
            'end_session.gte'     => 'Sesi selesai tidak boleh lebih kecil dari sesi mulai.',
            'end_time.after'      => 'Jam selesai harus lebih besar dari jam mulai.',
            'photo.mimes'         => 'Berkas izin harus berformat JPG, PNG, atau PDF.',
            'photo.max'           => 'Ukuran berkas izin maksimal 4 MB.',
        ]);

        $date = $validated['date'];

        if ($timeBased) {
            $startMin = Event::hhmmToMinutes($validated['start_time']);
            $endMin   = Event::hhmmToMinutes($validated['end_time']);

            if ($pesan = $this->outsideTimeMessage($date, $startMin, $endMin)) {
                return back()->withErrors(['start_time' => $pesan])->withInput();
            }

            $timeData = [
                'start_time'    => $validated['start_time'],
                'end_time'      => $validated['end_time'],
                'start_session' => null,
                'end_session'   => null,
            ];
        } else {
            $startSession = (int) $validated['start_session'];
            $endSession   = (int) $validated['end_session'];

            if ($pesan = $this->overSessionMessage($date, $startSession, $endSession)) {
                return back()->withErrors(['end_session' => $pesan])->withInput();
            }

            // Cek sesi lewat hanya jika tanggal/sesi mulai diubah (edit data lama tetap boleh).
            $berubah = $date !== $event->date->format('Y-m-d') || $startSession !== (int) $event->start_session;
            if ($berubah && ($pesan = $this->pastSessionMessage($date, $startSession))) {
                return back()->withErrors(['start_session' => $pesan])->withInput();
            }

            $range    = Event::sessionRangeMinutes($startSession, $endSession, $date);
            $startMin = $range[0];
            $endMin   = $range[1];

            $timeData = [
                'start_session' => $startSession,
                'end_session'   => $endSession,
                // Waktu lama berbasis jam dikosongkan; sekarang memakai sesi.
                'start_time'    => null,
                'end_time'      => null,
            ];
        }

        $bentrok = Event::overlappingByTime((int) $validated['room_id'], $date, $startMin, $endMin, $event->id)->isNotEmpty()
            || (!$timeBased && Event::conflicting(
                (int) $validated['room_id'], $date, $timeData['start_session'], $timeData['end_session'], $event->id
            )->exists());

        if ($bentrok) {
            return back()->withInput()->with('error', 'Gagal mengubah! Ruangan sudah dipesan pada rentang waktu tersebut.');
        }

        $data = $timeData + [
            'room_id'          => $validated['room_id'],
            'event_name'       => trim($validated['event_name']),
            'category'         => $validated['category'] ?? $event->category,
            'description'      => isset($validated['description']) && trim($validated['description']) !== ''
                ? trim($validated['description']) : $event->description,
            'responsible_name' => isset($validated['responsible_name']) && trim($validated['responsible_name']) !== ''
                ? trim($validated['responsible_name']) : $event->responsible_name,
            'date'             => $date,
            'is_outside_hours' => $timeBased || !Holidays::isSchoolDay($date),
        ];

        if ($request->hasFile('photo')) {
            if ($event->photo_path) {
                Storage::disk('public')->delete($event->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('events', 'public');
        }

        $event->update($this->existingColumns($data));

        return back()->with('success', 'Agenda berhasil diperbarui.');
    }

    /**
     * Menghapus agenda (khusus Waka/Admin).
     */
    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $this->authorize('delete', $event);

        if ($event->photo_path) {
            Storage::disk('public')->delete($event->photo_path);
        }

        $event->delete();

        return back()->with('success', 'Agenda berhasil dihapus.');
    }
}
