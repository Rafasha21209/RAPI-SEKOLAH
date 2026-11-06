<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Pusat aturan authorization modul agenda/peminjaman ruangan.
 * Dipakai controller (penegakan backend) & view (sembunyikan tombol).
 */
class EventPolicy
{
    /**
     * Mengajukan agenda: Guru/Organisasi, Siswa & Waka/Admin.
     * (Pembatasan jam sekolah untuk Siswa ditegakkan di EventController::store.)
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Guru/Organisasi', 'Siswa', 'Waka/Admin');
    }

    /** Menyetujui / menolak pengajuan: hanya Waka/Admin, dan hanya selama masih pending. */
    public function approve(User $user, Event $event): bool
    {
        return $user->isAdmin() && $event->isPending();
    }

    /** Memindahkan ruangan (hak veto): hanya Waka/Admin. */
    public function move(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /** Mengubah detail agenda (nama, ruangan, jadwal): hanya Waka/Admin. */
    public function update(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /** Menghapus agenda: hanya Waka/Admin. */
    public function delete(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Lapor kerusakan dari sebuah peminjaman: hanya peminjam (pemilik agenda) atau
     * Waka/Admin, hanya untuk agenda yang sudah disetujui dan tanggalnya sudah tiba
     * (ruangan sudah/sedang dipakai).
     */
    public function reportDamage(User $user, Event $event): bool
    {
        if (!$event->isApproved()) {
            return false;
        }

        if (!$user->isAdmin() && (int) $event->user_id !== (int) $user->id) {
            return false;
        }

        return $event->date->toDateString() <= now('Asia/Jakarta')->toDateString();
    }
}
