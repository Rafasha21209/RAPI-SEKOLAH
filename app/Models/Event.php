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

    /**
     * JADWAL JAM PELAJARAN (WIB) - sesuai jadwal sekolah.
     * Format: nomor sesi => [mulai, selesai]. Istirahat = jeda antar sesi (09:15-09:30, 11:30-12:30).
     * UBAH DI SINI bila jadwal sekolah berubah.
     */
    public const SCHEDULE_REGULAR = [   // Senin - Kamis (10 sesi, pulang 15:10)
        1  => ['07:15', '07:55'],
        2  => ['07:55', '08:35'],
        3  => ['08:35', '09:15'],
        4  => ['09:30', '10:10'],
        5  => ['10:10', '10:50'],
        6  => ['10:50', '11:30'],
        7  => ['12:30', '13:10'],
        8  => ['13:10', '13:50'],
        9  => ['13:50', '14:30'],
        10 => ['14:30', '15:10'],
    ];

    public const SCHEDULE_FRIDAY = [    // Jumat (8 sesi, pulang 13:50)
        1 => ['07:15', '07:55'],
        2 => ['07:55', '08:35'],
        3 => ['08:35', '09:15'],
        4 => ['09:30', '10:10'],
        5 => ['10:10', '10:50'],
        6 => ['10:50', '11:25'],
        7 => ['12:40', '13:15'],
        8 => ['13:15', '13:50'],
    ];

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

    /** Jadwal sesi untuk tanggal tertentu (Y-m-d). Jumat memakai jadwal Jumat; hari lain jadwal reguler. */
    public static function scheduleFor(?string $date = null): array
    {
        $isFriday = $date && \Carbon\Carbon::parse($date)->dayOfWeekIso === 5;

        return $isFriday ? self::SCHEDULE_FRIDAY : self::SCHEDULE_REGULAR;
    }

    /** Nomor sesi terakhir pada tanggal tsb (Jumat = 8, lainnya = 10). */
    public static function maxSessionFor(?string $date = null): int
    {
        return max(array_keys(self::scheduleFor($date)));
    }

    private static function toMinutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    /** Menit sejak 00:00 saat sesi $n dimulai pada tanggal $date; null bila sesi itu tidak ada hari tsb. */
    public static function sessionStartMinutes(int $n, ?string $date = null): ?int
    {
        $s = self::scheduleFor($date);

        return isset($s[$n]) ? self::toMinutes($s[$n][0]) : null;
    }

    /** Jam sesi, mis. sesi 1-3 => "07:15-09:15". */
    public static function sessionTimeRange(int $startSession, int $endSession, ?string $date = null): string
    {
        $s = self::scheduleFor($date);
        if (!isset($s[$startSession], $s[$endSession])) {
            return '-';
        }

        return $s[$startSession][0] . '-' . $s[$endSession][1];
    }

    /**
     * Apakah sesi $session pada tanggal $date (Y-m-d) sudah dimulai/lewat menurut jam WIB sekarang?
     * Tanggal besok atau setelahnya selalu false; tanggal kemarin atau sebelumnya selalu true.
     */
    public static function isSessionPast(string $date, int $session): bool
    {
        $now   = \Carbon\Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();

        if ($date < $today) {
            return true;
        }
        if ($date > $today) {
            return false;
        }

        $start = self::sessionStartMinutes($session, $date);

        return $start !== null && $start <= ($now->hour * 60 + $now->minute);
    }

    /** Label waktu pinjam: "Sesi 3 · 08:30-09:15"; data lama tetap tampil jamnya. */
    public function sessionLabel(): string
    {
        if ($this->start_session) {
            $label = $this->start_session === $this->end_session
                ? 'Sesi ' . $this->start_session
                : 'Sesi ' . $this->start_session . '-' . $this->end_session;

            return $label . ' · ' . self::sessionTimeRange($this->start_session, $this->end_session, $this->date?->format('Y-m-d'));
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
