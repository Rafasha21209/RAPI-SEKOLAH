<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

/**
 * Pusat aturan authorization modul pengaduan kerusakan fasilitas.
 *
 * Dipakai baik oleh Controller (penegakan di BACKEND) maupun oleh View
 * (hanya untuk menyembunyikan tombol), sehingga aturannya tidak kembar
 * dan tidak bisa dilewati dengan memanipulasi room_id / complaint_id.
 */
class ComplaintPolicy
{
    /**
     * Membuat pengaduan: Guru/Organisasi (dan Waka/Admin sebagai pelapor internal).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Guru/Organisasi', 'Waka/Admin');
    }

    /**
     * Melihat detail satu pengaduan.
     * PIC hanya boleh melihat pengaduan dari ruangan tanggung jawabnya.
     */
    public function view(User $user, Complaint $complaint): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isPic()) {
            return $complaint->room !== null && $complaint->room->isManagedBy($user);
        }

        // Guru/Organisasi: boleh melihat daftar (monitoring laporan), tanpa hak ubah.
        return $user->isReporter();
    }

    /**
     * Mengubah status perbaikan.
     *
     * Sesuai soal UKK: yang mengubah status HANYA PIC Ruang yang
     * bertanggung jawab atas ruangan pengaduan tersebut.
     * Waka/Admin TIDAK diberi hak ini (hanya monitoring + WA warning).
     */
    public function updateStatus(User $user, Complaint $complaint): bool
    {
        if (!$user->isPic()) {
            return false;
        }

        if ($complaint->room === null || !$complaint->room->isManagedBy($user)) {
            return false;
        }

        // Status final tidak boleh diubah lagi.
        return !$complaint->isSelesai();
    }

    /**
     * Mengirim WA Warning ke PIC: hanya Waka/Admin, hanya jika pengaduan
     * masih "Belum Diperbaiki" dan sudah melewati batas waktu penanganan.
     */
    public function sendWaWarning(User $user, Complaint $complaint): bool
    {
        return $user->isAdmin() && $complaint->isOverdue();
    }

    /**
     * Menghapus (soft delete) pengaduan.
     * Hanya Waka/Admin dan hanya untuk pengaduan yang sudah selesai,
     * agar histori penanganan yang masih berjalan tidak hilang.
     */
    public function delete(User $user, Complaint $complaint): bool
    {
        return $user->isAdmin() && $complaint->isSelesai();
    }
}
