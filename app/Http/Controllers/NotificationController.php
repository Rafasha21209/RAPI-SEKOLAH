<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Notifikasi untuk user yang login (hasil pengajuan peminjaman, laporan kerusakan,
 * status perbaikan, pesan dari Waka/Admin). Setiap user hanya bisa melihat notifikasinya sendiri.
 */
class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->limit(100)
            ->get();

        // Daftar PIC untuk form "Kirim Notifikasi" (hanya dipakai Waka/Admin).
        $pics = Auth::user()->isAdmin() ? $this->picQuery()->with('rooms')->orderBy('fullname')->get() : collect();

        return view('notifications.index', compact('notifications', 'pics'));
    }

    public function readAll()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Waka/Admin mengirim notifikasi ke satu PIC Ruang atau ke semua PIC.
     * Role dicek di route DAN di sini; tujuan hanya boleh user ber-role "PIC Ruang".
     */
    public function send(Request $request)
    {
        $admin = Auth::user();
        abort_unless($admin->isAdmin(), 403);

        $validated = $request->validate([
            'pic_id'  => ['required', 'string'],
            'title'   => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'pic_id.required'  => 'Pilih PIC tujuan.',
            'title.required'   => 'Judul notifikasi wajib diisi.',
            'message.required' => 'Isi pesan wajib diisi.',
            'message.min'      => 'Isi pesan minimal 5 karakter.',
            'message.max'      => 'Isi pesan maksimal 500 karakter.',
        ]);

        $query = $this->picQuery();
        if ($validated['pic_id'] !== 'all') {
            $query->where('id', (int) $validated['pic_id']);
        }
        $pics = $query->get();

        if ($pics->isEmpty()) {
            return back()->withInput()->withErrors(['pic_id' => 'PIC tujuan tidak ditemukan.']);
        }

        $pengirim = $admin->fullname ?: $admin->username;

        DB::transaction(function () use ($pics, $validated, $pengirim) {
            foreach ($pics as $pic) {
                Notification::create([
                    'user_id' => $pic->id,
                    'title'   => $validated['title'],
                    'message' => $validated['message'] . ' (dari Waka/Admin: ' . $pengirim . ')',
                ]);
            }
        });

        $tujuan = $pics->count() === 1
            ? ($pics->first()->fullname ?: $pics->first()->username)
            : $pics->count() . ' PIC';

        return back()->with('success', 'Notifikasi terkirim ke ' . $tujuan . '.');
    }

    /** Semua user ber-role PIC Ruang. */
    private function picQuery()
    {
        return User::whereHas('role', fn ($q) => $q->where('role_name', 'PIC Ruang'));
    }
}
