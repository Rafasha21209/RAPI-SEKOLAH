<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintStatusLog;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MODUL PENGADUAN KERUSAKAN FASILITAS (RAPI-Sekolah)
 *
 * Alur logika:
 *  Guru/Organisasi  -> membuat laporan (room_id, deskripsi, foto bukti)
 *  Sistem           -> menentukan ruangan + PIC dari rooms.pic_id, status awal "Belum Diperbaiki"
 *  PIC Ruang        -> menangani, mengubah status (Menunggu Spare Part / Sudah Diperbaiki)
 *  Waka/Admin       -> monitoring; jika masih "Belum Diperbaiki" & lewat batas waktu,
 *                      kirim WA Warning ke PIC ruangan tersebut.
 *
 * Seluruh pengecekan role & kepemilikan ruangan dilakukan di BACKEND
 * (ComplaintPolicy), bukan hanya menyembunyikan tombol di frontend.
 */
class ComplaintController extends Controller
{
    /**
     * Menampilkan daftar pengaduan sesuai role.
     *
     * - PIC Ruang      : HANYA pengaduan dari ruangan yang pic_id-nya = dirinya.
     *                    (satu PIC boleh memegang beberapa ruangan -> semua tampil)
     * - Waka/Admin     : seluruh pengaduan (monitoring).
     * - Guru/Organisasi: seluruh pengaduan (read-only + form lapor).
     */
    public function index()
    {
        $user = Auth::user();

        $query = Complaint::with(['room.pic', 'pic', 'event', 'reporter', 'handler']);

        if ($user->isPic() && !$user->isAllRoomsPic()) {
            // Batasi di level QUERY, bukan hanya di view.
            // Ruangan tanpa PIC (pic_id null) TIDAK ditampilkan agar tidak
            // terjadi kebocoran data antar PIC.
            $query->forPic($user->id);
        }

        // Terbaru lebih dulu; data lama tetap dapat dibuka lewat halaman berikutnya.
        $complaints = $query->orderByDesc('reported_at')->orderByDesc('id')->paginate(15)->withQueryString();

        // Pilihan ruangan pada form lapor.
        // PIC hanya butuh ruangannya; pelapor butuh semua ruangan aktif.
        $rooms = Room::with('pic')
            ->where('is_active', true)
            ->when($user->isPic() && !$user->isAllRoomsPic(), fn ($q) => $q->where('pic_id', $user->id))
            ->orderBy('id')
            ->get();

        return view('complaints.index', compact('complaints', 'rooms'));
    }

    /**
     * Menyimpan laporan pengaduan kerusakan baru + upload foto bukti.
     *
     * Validasi dilakukan di backend:
     *  - room_id wajib ada di tabel rooms (mencegah manipulasi room_id palsu)
     *  - deskripsi minimal 10 karakter
     *  - foto wajib image, tipe jpeg/png/jpg, maksimal 2 MB
     */
    public function store(Request $request)
    {
        $this->authorize('create', Complaint::class);

        $validated = $request->validate([
            // Jika dilapor dari peminjaman, room_id boleh dikosongkan: ruangan diambil otomatis dari event.
            'event_id'    => ['nullable', 'integer', Rule::exists('events', 'id')],
            'room_id'     => ['required_without:event_id', 'nullable', 'integer', Rule::exists('rooms', 'id')],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'photo'       => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ], [
            'room_id.required'     => 'Ruangan wajib dipilih.',
            'room_id.exists'       => 'Ruangan yang dipilih tidak valid.',
            'description.required' => 'Deskripsi kerusakan wajib diisi.',
            'description.min'      => 'Deskripsi kerusakan minimal 10 karakter.',
            'photo.required'       => 'Foto bukti fisik wajib diunggah.',
            'photo.image'          => 'File yang diunggah harus berupa gambar.',
            'photo.mimes'          => 'Format foto harus JPG, JPEG, atau PNG.',
            'photo.max'            => 'Ukuran foto maksimal 2 MB.',
        ]);

        // Laporan dari peminjaman: data ruangan + pelapor (peminjam) otomatis dari event.
        $event = null;
        if (!empty($validated['event_id'])) {
            $event = Event::with('room')->findOrFail($validated['event_id']);
            $this->authorize('reportDamage', $event);
            $validated['room_id'] = $event->room_id;
        }

        // Pastikan ruangan memang aktif (tidak boleh melapor ruangan non-aktif).
        $room = Room::with('pic')->findOrFail($validated['room_id']);
        if (isset($room->is_active) && !$room->is_active) {
            throw ValidationException::withMessages([
                'room_id' => 'Ruangan tersebut sedang tidak aktif.',
            ]);
        }

        $photoPath = $request->file('photo')->store('complaints', 'public');

        DB::transaction(function () use ($validated, $photoPath, $room, $event) {
            $complaint = Complaint::create([
                'room_id'       => $room->id,
                'event_id'      => $event?->id,
                'pic_id'        => $room->responsiblePic()?->id, // PIC otomatis: PIC ruangan, atau PIC semua ruangan
                'reporter_id'   => Auth::id(), // selalu dari session, tidak dari input
                'description'   => $validated['description'],
                'photo_path'    => $photoPath,
                'status_repair' => Complaint::STATUS_BELUM, // status awal wajib
                'reported_at'   => now(),
            ]);

            // Catat histori awal + PIC yang bertanggung jawab saat laporan dibuat.
            ComplaintStatusLog::create([
                'complaint_id' => $complaint->id,
                'actor_id'     => Auth::id(),
                'action'       => 'created',
                'status_from'  => null,
                'status_to'    => Complaint::STATUS_BELUM,
                'note'         => 'Pengaduan dibuat'
                    . ($event ? ' dari peminjaman "' . $event->event_name . '"' : '')
                    . '. PIC: ' . ($room->responsiblePic()->fullname ?? 'belum ditentukan') . '.',
            ]);

            // Notifikasi otomatis ke PIC ruangan.
            foreach ($room->notifiablePicIds() as $picId) {
                Notification::create([
                    'user_id' => $picId,
                    'title'   => 'Laporan Kerusakan Baru',
                    'message' => 'Ruangan ' . $room->room_name . ': ' . $validated['description']
                        . ' (pelapor: ' . (Auth::user()->fullname ?? Auth::user()->username) . ').',
                ]);
            }
        });

        $picInfo = $room->responsiblePic()
            ? 'Pengaduan diteruskan ke PIC ' . $room->responsiblePic()->fullname . '.'
            : 'Ruangan ini belum memiliki PIC, silakan hubungi Waka/Admin.';

        return redirect()
            ->route('complaints.index')
            ->with('success', 'Laporan pengaduan berhasil dikirim. ' . $picInfo);
    }

    /**
     * Memperbarui status perbaikan.
     *
     * HANYA PIC Ruang pemilik ruangan (dicek di ComplaintPolicy::updateStatus).
     * Transisi status dibatasi:
     *   Belum Diperbaiki    -> Menunggu Spare Part | Sudah Diperbaiki
     *   Menunggu Spare Part -> Sudah Diperbaiki
     * Data pengaduan lama TIDAK dihapus; perubahan dicatat di complaint_status_logs.
     */
    public function updateStatus(Request $request, $id)
    {
        $complaint = Complaint::with('room')->findOrFail($id);

        // Authorization sebelum update (mencegah manipulasi complaint_id).
        $this->authorize('updateStatus', $complaint);

        $validated = $request->validate([
            'status_repair' => ['required', 'string', Rule::in(Complaint::STATUSES)],
            'note'          => ['nullable', 'string', 'max:255'],
            // Foto bukti perbaikan WAJIB saat status diubah menjadi "Sudah Diperbaiki".
            'repair_photo'  => [
                Rule::requiredIf(fn () => $request->input('status_repair') === Complaint::STATUS_SELESAI),
                'nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048',
            ],
        ], [
            'status_repair.in'      => 'Status perbaikan tidak valid.',
            'repair_photo.required' => 'Foto bukti perbaikan wajib diunggah saat status "Sudah Diperbaiki".',
            'repair_photo.image'    => 'Bukti perbaikan harus berupa gambar.',
            'repair_photo.mimes'    => 'Format foto harus JPG, JPEG, atau PNG.',
            'repair_photo.max'      => 'Ukuran foto maksimal 2 MB.',
        ]);

        $statusBaru = $validated['status_repair'];
        $statusLama = $complaint->status_repair;

        // Validasi alur/workflow status di backend.
        if ($statusBaru === $statusLama) {
            return back()->with('error', 'Status perbaikan tidak berubah.');
        }

        if (!$complaint->canTransitionTo($statusBaru)) {
            $diizinkan = $complaint->allowedNextStatuses();

            return back()->with('error', sprintf(
                'Perubahan status dari "%s" ke "%s" tidak diperbolehkan. Status yang tersedia: %s.',
                $statusLama,
                $statusBaru,
                $diizinkan ? implode(', ', $diizinkan) : 'tidak ada (status sudah final)'
            ));
        }

        $repairPhoto = $statusBaru === Complaint::STATUS_SELESAI && $request->hasFile('repair_photo')
            ? $request->file('repair_photo')->store('repairs', 'public')
            : null;

        DB::transaction(function () use ($complaint, $statusBaru, $statusLama, $validated, $repairPhoto) {
            $complaint->update([
                'status_repair'     => $statusBaru,
                'handler_id'        => Auth::id(), // PIC yang menangani
                'repaired_at'       => $statusBaru === Complaint::STATUS_SELESAI ? now() : null,
                'repair_photo_path' => $repairPhoto ?? $complaint->repair_photo_path,
            ]);

            // Beri tahu pelapor bahwa kerusakan sudah ditangani / status berubah.
            if ($complaint->reporter_id) {
                Notification::create([
                    'user_id' => $complaint->reporter_id,
                    'title'   => $statusBaru === Complaint::STATUS_SELESAI ? 'Kerusakan Sudah Diperbaiki' : 'Status Laporan Kerusakan Berubah',
                    'message' => 'Laporan kerusakan di ' . ($complaint->room->room_name ?? 'ruangan') . ' kini berstatus "' . $statusBaru . '".',
                ]);
            }

            ComplaintStatusLog::create([
                'complaint_id' => $complaint->id,
                'actor_id'     => Auth::id(),
                'action'       => 'status_change',
                'status_from'  => $statusLama,
                'status_to'    => $statusBaru,
                'note'         => $validated['note'] ?? null,
            ]);
        });

        return back()->with('success', 'Status perbaikan diperbarui menjadi "' . $statusBaru . '".');
    }

    /**
     * Mengirim peringatan WhatsApp ke PIC ruangan (khusus Waka/Admin).
     *
     * Syarat (dicek ulang di backend, bukan hanya tombol di frontend):
     *  - role Waka/Admin
     *  - status pengaduan masih "Belum Diperbaiki"
     *  - sudah melewati batas waktu penanganan (Complaint::REPAIR_DEADLINE_HOURS)
     * Pesan ditujukan ke nomor WhatsApp PIC dari data user di database.
     */
    public function sendWaWarning($id)
    {
        $complaint = Complaint::with('room.pic')->findOrFail($id);

        // Pesan error yang informatif bila syarat waktu/status belum terpenuhi.
        if ($complaint->isSelesai()) {
            return back()->with('error', 'Pengaduan sudah "Sudah Diperbaiki", peringatan tidak diperlukan.');
        }

        if ($complaint->status_repair !== Complaint::STATUS_BELUM) {
            return back()->with('error', 'Peringatan hanya untuk pengaduan berstatus "Belum Diperbaiki".');
        }

        if (!$complaint->isOverdue()) {
            $deadline = $complaint->repairDeadline();

            return back()->with('error', 'Pengaduan ini belum melewati batas waktu penanganan ('
                . Complaint::REPAIR_DEADLINE_HOURS . ' jam). Batas waktu: '
                . ($deadline ? $deadline->format('d/m/Y H:i') : '-') . '.');
        }

        // Authorization final (role + kondisi overdue).
        $this->authorize('sendWaWarning', $complaint);

        $pic = $complaint->room?->responsiblePic();

        if (!$pic || empty($pic->phone_number)) {
            return back()->with('error', 'Ruangan ini belum memiliki PIC atau nomor WhatsApp PIC belum terdaftar.');
        }

        $phone = $this->normalizePhone($pic->phone_number);
        if ($phone === null) {
            return back()->with('error', 'Nomor WhatsApp PIC tidak valid: ' . $pic->phone_number);
        }

        $waktuLapor = $complaint->reported_at ?? $complaint->created_at;

        $message = "*PERINGATAN PENANGANAN KERUSAKAN - RAPI Sekolah*\n"
            . "Halo " . $pic->fullname . ",\n\n"
            . "Terdapat pengaduan kerusakan fasilitas pada ruangan yang menjadi tanggung jawab Anda "
            . "dan belum ditangani hingga melewati batas waktu " . Complaint::REPAIR_DEADLINE_HOURS . " jam.\n\n"
            . "Ruangan   : " . ($complaint->room->room_name ?? '-') . "\n"
            . "Lokasi    : " . ($complaint->room->location ?? '-') . "\n"
            . "Kerusakan : " . $complaint->description . "\n"
            . "Pelapor   : " . ($complaint->reporter->fullname ?? '-') . "\n"
            . "Waktu Lapor : " . ($waktuLapor ? $waktuLapor->format('d/m/Y H:i') : '-') . "\n"
            . "Status    : " . $complaint->status_repair . "\n\n"
            . "Mohon segera menangani laporan tersebut dan memperbarui status perbaikan pada sistem RAPI-Sekolah.\n"
            . "Terima kasih.";

        // Catat histori pengiriman warning (audit trail) TANPA mengubah status.
        DB::transaction(function () use ($complaint) {
            $complaint->update([
                'last_warned_at' => now(),
                'warning_count'  => (int) $complaint->warning_count + 1,
            ]);

            ComplaintStatusLog::create([
                'complaint_id' => $complaint->id,
                'actor_id'     => Auth::id(),
                'action'       => 'wa_warning',
                'status_from'  => $complaint->status_repair,
                'status_to'    => $complaint->status_repair,
                'note'         => 'WA Warning dikirim ke PIC ruangan.',
            ]);
        });

        return redirect()->away('https://wa.me/' . $phone . '?text=' . urlencode($message));
    }

    /**
     * Menghapus (soft delete) pengaduan — hanya Waka/Admin & hanya yang sudah selesai.
     *
     * Menggunakan soft delete agar data pengaduan lama tetap tersimpan dan
     * dapat ditelusuri (syarat traceability soal UKK). Foto bukti juga tidak
     * dihapus dari storage agar histori tetap utuh.
     */
    public function destroy($id)
    {
        $complaint = Complaint::with('room')->findOrFail($id);

        $this->authorize('delete', $complaint);

        ComplaintStatusLog::create([
            'complaint_id' => $complaint->id,
            'actor_id'     => Auth::id(),
            'action'       => 'archived',
            'status_from'  => $complaint->status_repair,
            'status_to'    => $complaint->status_repair,
            'note'         => 'Pengaduan diarsipkan (soft delete) oleh Waka/Admin.',
        ]);

        $complaint->delete(); // soft delete

        return back()->with('success', 'Pengaduan diarsipkan. Data histori tetap tersimpan.');
    }

    /**
     * Normalisasi nomor telepon Indonesia ke format wa.me (62xxxxxxxxxx).
     */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '62')) {
            $digits = '62' . $digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }
}
