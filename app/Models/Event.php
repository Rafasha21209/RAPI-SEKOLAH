<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * MODUL AGENDA / PEMINJAMAN RUANGAN (RAPI-Sekolah)
 *
 * Alur logika:
 *  Guru/Organisasi -> mengajukan agenda, status awal WAJIB "pending"
 *  Waka/Admin      -> menyetujui (approved) / menolak (rejected), hak veto pindah ruang
 *  Publik          -> hanya melihat agenda yang SUDAH "approved" (lihat scopeApproved)
 *
 * Validasi bentrok jadwal (scopeConflicting) mempertimbangkan agenda
 * berstatus "approved" MAUPUN "pending" — supaya slot yang masih menunggu
 * approval pun tidak bisa direbut oleh pengajuan lain pada jam yang sama.
 */
class Event extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /** Waktu pinjam memakai sesi 1 sampai 10 (bukan jam). */
    public const SESSION_MIN = 1;
    public const SESSION_MAX = 10;

    protected $fillable = [
        'room_id',
        'user_id',
        'event_name',
        'date',
        'start_time',
        'end_time',
        'status',
        'approved_by',
        'move_reason',
        'reject_reason',
        'photo_path',
        'start_session',
        'end_session',
    ];

    protected $casts = [
        'date'          => 'date',
        'start_session' => 'integer',
        'end_session'   => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */
    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'event_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scope
    |--------------------------------------------------------------------------
    */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Bentrok jika ruangan & tanggal sama dan rentang sesi saling beririsan.
     * Agenda lama yang belum punya sesi (data berbasis jam) tidak ikut dihitung.
     */
    public function scopeConflicting(
        Builder $query,
        int $roomId,
        string $date,
        int $startSession,
        int $endSession,
        ?int $excludeId = null
    ): Builder {
        return $query->where('room_id', $roomId)
            ->where('date', $date)
            ->where('status', '!=', self::STATUS_REJECTED)
            ->whereNotNull('start_session')
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->where('start_session', '<=', $endSession)
            ->where('end_session', '>=', $startSession);
    }

    /** Label waktu pinjam: "Sesi 3" / "Sesi 1-4"; data lama tetap tampil jamnya. */
    public function sessionLabel(): string
    {
        if ($this->start_session) {
            return $this->start_session === $this->end_session
                ? 'Sesi ' . $this->start_session
                : 'Sesi ' . $this->start_session . '-' . $this->end_session;
        }

        if ($this->start_time) {
            return substr($this->start_time, 0, 5) . ' - ' . substr((string) $this->end_time, 0, 5);
        }

        return '-';
    }

    /*
    |--------------------------------------------------------------------------
    | Helper status (FIX: case-insensitive)
    |--------------------------------------------------------------------------
    | Perbandingan "===" di PHP itu case-sensitive, sedangkan query SQL
    | (di atas) default-nya case-insensitive di MySQL. Supaya keduanya
    | konsisten walau ada data lama yang status-nya "Pending"/"Approved"
    | (huruf besar), method di bawah ini menormalkan dulu ke huruf kecil.
    */
    public function normalizedStatus(): string
    {
        return strtolower(trim((string) $this->status));
    }

    public function isPending(): bool
    {
        return !in_array($this->normalizedStatus(), [
            strtolower(self::STATUS_APPROVED),
            strtolower(self::STATUS_REJECTED),
        ], true);
    }

    public function isApproved(): bool
    {
        return $this->normalizedStatus() === strtolower(self::STATUS_APPROVED);
    }
}
