<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Notifikasi untuk user yang login (hasil pengajuan peminjaman, laporan kerusakan,
 * status perbaikan). Setiap user hanya bisa melihat notifikasinya sendiri.
 */
class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->limit(100)
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    public function readAll()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
