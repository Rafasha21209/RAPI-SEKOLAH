<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
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

    /**
     * Menampilkan daftar seluruh agenda (dashboard internal, wajib login).
     * Semua role yang login boleh melihat; hak ubah/hapus diatur per baris di view.
     */
    public function index()
    {
        $events = Event::with(['room', 'user'])
            ->orderBy('date')
            ->orderBy('start_session')
            ->orderBy('start_time')
            ->get();

        $rooms = Room::where('is_active', true)->orderBy('id')->get();

        return view('events.index', compact('events', 'rooms'));
    }

    /**
     * KETENTUAN: Endpoint JSON untuk FullCalendar.
     * HANYA agenda berstatus "approved" yang boleh ditarik ke sini —
     * agenda yang belum dikonfirmasi Admin/Waka TIDAK BOLEH muncul di
     * kalender publik. Route ini PUBLIK (tanpa login), lihat routes/web.php.
     */
    public function calendarFeed()
    {
        $events = Event::approved()
            ->with('room')
            ->get()
            ->map(function (Event $event) {
                $tanggal = $event->date->format('Y-m-d');

                $item = [
                    'id'    => $event->id,
                    'title' => $event->event_name . ' — ' . ($event->room->room_name ?? 'Ruangan tidak diketahui')
                        . ' (' . $event->sessionLabel() . ')',
                    'extendedProps' => [
                        'room'     => $event->room->room_name ?? '-',
                        'location' => $event->room->location ?? null,
                        'session'  => $event->sessionLabel(),
                    ],
                ];

                if ($event->start_session) {
                    // Waktu pinjam berbasis sesi -> tampil sebagai agenda sehari penuh.
                    $item['start']  = $tanggal;
                    $item['allDay'] = true;
                } else {
                    // Data lama berbasis jam.
                    $item['start'] = $tanggal . 'T' . $event->start_time;
                    $item['end']   = $tanggal . 'T' . $event->end_time;
                }

                return $item;
            })
            ->values();

        return response()->json($events);
    }

    /**
     * KETENTUAN: Guru dapat mengajukan agenda, status awal WAJIB "pending"
     * (tidak bisa dipilih dari input) dan wajib menunggu approval Admin/Waka.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $validated = $request->validate([
            'room_id'    => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'event_name' => ['required', 'string', 'max:255'],
            'date'       => ['required', 'date', 'after_or_equal:' . $this->minDateFor(Auth::user())],
            'start_session' => ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX],
            'end_session'   => ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX, 'gte:start_session'],
            'photo'      => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'date.after_or_equal' => Auth::user()->isAdmin()
                ? 'Tanggal peminjaman tidak boleh sebelum hari ini.'
                : 'Peminjaman harus diajukan minimal H-1 (paling cepat untuk tanggal ' . Carbon::tomorrow('Asia/Jakarta')->format('d/m/Y') . ').',
            'start_session.required' => 'Sesi mulai wajib dipilih.',
            'end_session.required'   => 'Sesi selesai wajib dipilih.',
            'start_session.between'  => 'Sesi mulai harus antara 1 sampai 10.',
            'end_session.between'    => 'Sesi selesai harus antara 1 sampai 10.',
            'end_session.gte'        => 'Sesi selesai tidak boleh lebih kecil dari sesi mulai.',
            'photo.required' => 'Foto izin peminjaman wajib diunggah.',
        ]);

        if ($pesan = $this->overSessionMessage($validated['date'], (int) $validated['start_session'], (int) $validated['end_session'])) {
            return back()->withErrors(['end_session' => $pesan])->withInput();
        }

        // Sesi yang jamnya sudah lewat (menurut jam WIB sekarang) tidak boleh dipilih untuk hari ini.
        if ($pesan = $this->pastSessionMessage($validated['date'], (int) $validated['start_session'])) {
            return back()->withErrors(['start_session' => $pesan])->withInput();
        }

        // KETENTUAN: validasi bentrok jadwal mempertimbangkan agenda
        // APPROVED maupun PENDING (lihat Event::scopeConflicting).
        $bentrok = Event::conflicting(
            $validated['room_id'],
            $validated['date'],
            (int) $validated['start_session'],
            (int) $validated['end_session']
        )->exists();

        if ($bentrok) {
            return back()->withInput()->with(
                'error',
                'Gagal mengajukan! Ruangan sudah dipesan (baik yang sudah disetujui maupun yang masih menunggu approval) pada rentang waktu tersebut.'
            );
        }

        $photoPath = $request->file('photo')->store('events', 'public');

        $event = Event::create([
            'room_id'    => $validated['room_id'],
            'user_id'    => Auth::id(),
            'event_name' => $validated['event_name'],
            'date'       => $validated['date'],
            'start_session' => (int) $validated['start_session'],
            'end_session'   => (int) $validated['end_session'],
            'status'     => Event::STATUS_PENDING, // WAJIB pending, bukan dari input user
            'photo_path' => $photoPath,
        ]);

        // Notifikasi ke peminjam: pengajuan berhasil dikirim.
        Notification::create([
            'user_id' => Auth::id(),
            'title'   => 'Pengajuan Peminjaman Terkirim',
            'message' => 'Pengajuan agenda "' . $event->event_name . '" (' . $event->date->format('d/m/Y')
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
        if ($validated['status'] === Event::STATUS_APPROVED && $event->start_session) {
            $bentrok = Event::conflicting(
                $event->room_id,
                $event->date->format('Y-m-d'),
                $event->start_session,
                $event->end_session,
                $event->id
            )->where('status', Event::STATUS_APPROVED)->exists();

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

        $bentrok = $event->start_session && Event::conflicting(
            $validated['new_room_id'],
            $event->date->format('Y-m-d'),
            $event->start_session,
            $event->end_session,
            $event->id
        )->exists();

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

        // Tanggal baru tidak boleh di masa lalu. Kalau tanggalnya tidak diubah
        // (admin hanya mengganti nama/jam/ruangan agenda lama), tidak dicek.
        $dateRules = ['required', 'date'];
        if ($request->input('date') !== $event->date->format('Y-m-d')) {
            $dateRules[] = 'after_or_equal:' . $this->todayWib();
        }

        $validated = $request->validate([
            'room_id'    => ['required', 'integer', Rule::exists('rooms', 'id')->where('is_active', true)],
            'event_name' => ['required', 'string', 'max:255'],
            'date'       => $dateRules,
            'start_session' => ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX],
            'end_session'   => ['required', 'integer', 'between:' . Event::SESSION_MIN . ',' . Event::SESSION_MAX, 'gte:start_session'],
            'photo'      => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'date.after_or_equal' => 'Tanggal baru tidak boleh sebelum hari ini.',
            'end_session.gte'     => 'Sesi selesai tidak boleh lebih kecil dari sesi mulai.',
        ]);

        if ($pesan = $this->overSessionMessage($validated['date'], (int) $validated['start_session'], (int) $validated['end_session'])) {
            return back()->withErrors(['end_session' => $pesan])->withInput();
        }

        // Cek sesi lewat hanya jika tanggal/sesi mulai diubah (edit data lama tetap boleh).
        $berubah = $validated['date'] !== $event->date->format('Y-m-d')
            || (int) $validated['start_session'] !== (int) $event->start_session;
        if ($berubah && ($pesan = $this->pastSessionMessage($validated['date'], (int) $validated['start_session']))) {
            return back()->withErrors(['start_session' => $pesan])->withInput();
        }

        $bentrok = Event::conflicting(
            $validated['room_id'],
            $validated['date'],
            (int) $validated['start_session'],
            (int) $validated['end_session'],
            $event->id
        )->exists();

        if ($bentrok) {
            return back()->withInput()->with('error', 'Gagal mengubah! Ruangan sudah dipesan pada rentang waktu tersebut.');
        }

        $data = [
            'room_id'    => $validated['room_id'],
            'event_name' => $validated['event_name'],
            'date'       => $validated['date'],
            'start_session' => (int) $validated['start_session'],
            'end_session'   => (int) $validated['end_session'],
            // Waktu lama berbasis jam dikosongkan; sekarang memakai sesi.
            'start_time' => null,
            'end_time'   => null,
        ];

        if ($request->hasFile('photo')) {
            if ($event->photo_path) {
                Storage::disk('public')->delete($event->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('events', 'public');
        }

        $event->update($data);

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
