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

    /** Kategori yang bisa dipilih pada form pengajuan. */
    public const CATEGORIES = ['Acara Sekolah', 'Kegiatan Guru', 'Kegiatan Siswa', 'Hari Libur'];

    /**
     * Jenis event pada kalender: warna + ikon + label (satu sumber untuk kalender, legenda, tabel).
     * Pengajuan tanpa kategori (data lama / peminjaman biasa) = 'peminjaman'.
     */
    public const TYPE_META = [
        'acara_sekolah'  => ['label' => 'Acara Sekolah',        'color' => '#2e7d32'],
        'kegiatan_guru'  => ['label' => 'Kegiatan Guru',        'color' => '#1565c0'],
        'kegiatan_siswa' => ['label' => 'Kegiatan Siswa',       'color' => '#e08a00'],
        'hari_libur'     => ['label' => 'Hari Libur Sekolah',   'color' => '#ad1457'],
        'libur_nasional' => ['label' => 'Hari Libur Nasional',  'color' => '#c62828'],
        'peminjaman'     => ['label' => 'Peminjaman Ruangan',   'color' => '#546e7a'],
    ];

    protected $fillable = [
        'room_id',
        'user_id',
        'event_name',
        'category',
        'description',
        'notes',
        'responsible_name',
        'is_outside_hours',
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
        'is_outside_hours' => 'boolean',
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
            $range = substr($this->start_time, 0, 5) . ' - ' . substr((string) $this->end_time, 0, 5);

            return $this->is_outside_hours ? 'Luar jam sekolah, ' . $range : $range;
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

    /*
    |--------------------------------------------------------------------------
    | Jenis event (untuk warna/ikon) & waktu bebas (di luar jam sekolah)
    |--------------------------------------------------------------------------
    */
    public function typeKey(): string
    {
        return match ($this->category) {
            'Acara Sekolah'  => 'acara_sekolah',
            'Kegiatan Guru'  => 'kegiatan_guru',
            'Kegiatan Siswa' => 'kegiatan_siswa',
            'Hari Libur'     => 'hari_libur',
            default          => 'peminjaman',
        };
    }

    public function typeMeta(): array
    {
        return self::TYPE_META[$this->typeKey()];
    }

    /** Menit sejak 00:00 dari 'HH:MM' / 'HH:MM:SS'. */
    public static function hhmmToMinutes(string $hhmm): int
    {
        return self::toMinutes(substr($hhmm, 0, 5));
    }

    /** Rentang jam sekolah satu hari [menit mulai sesi 1, menit selesai sesi terakhir]. */
    public static function schoolSpanMinutes(string $date): array
    {
        $s = self::scheduleFor($date);

        return [self::toMinutes($s[min(array_keys($s))][0]), self::toMinutes($s[max(array_keys($s))][1])];
    }

    /** Rentang waktu [mulai, selesai] dalam menit untuk sesi mulai..selesai pada tanggal tsb. */
    public static function sessionRangeMinutes(int $startSession, int $endSession, string $date): ?array
    {
        $s = self::scheduleFor($date);
        if (!isset($s[$startSession], $s[$endSession])) {
            return null;
        }

        return [self::toMinutes($s[$startSession][0]), self::toMinutes($s[$endSession][1])];
    }

    /** Rentang waktu agenda ini dalam menit (sesi atau jam bebas); null bila tidak diketahui. */
    public function rangeMinutes(): ?array
    {
        $date = $this->date?->format('Y-m-d');

        if ($this->start_session && $date) {
            return self::sessionRangeMinutes((int) $this->start_session, (int) $this->end_session, $date);
        }

        if ($this->start_time && $this->end_time) {
            return [self::hhmmToMinutes($this->start_time), self::hhmmToMinutes($this->end_time)];
        }

        return null;
    }

    /**
     * Agenda (selain yang ditolak) yang beririsan waktu dengan [$startMin, $endMin) pada ruangan & tanggal tsb.
     * Mencakup agenda berbasis sesi MAUPUN berbasis jam bebas (di luar jam sekolah).
     */
    public static function overlappingByTime(int $roomId, string $date, int $startMin, int $endMin, ?int $excludeId = null)
    {
        return self::where('room_id', $roomId)
            ->where('date', $date)
            ->where('status', '!=', self::STATUS_REJECTED)
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->filter(function (Event $e) use ($startMin, $endMin) {
                $r = $e->rangeMinutes();

                return $r !== null && $r[0] < $endMin && $r[1] > $startMin;
            })
            ->values();
    }

    /** 'HH:MM' dari menit. */
    public static function minutesToHhmm(int $m): string
    {
        return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }

    /** Teks status yang ramah tampilan: Approved | Rejected | Pending. */
    public function statusText(): string
    {
        if ($this->isApproved()) {
            return 'Approved';
        }

        return $this->normalizedStatus() === 'rejected' ? 'Rejected' : 'Pending';
    }

    /** URL foto/dokumen izin, atau null. */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/' . $this->photo_path) : null;
    }

    /**
     * Data untuk modal detail (partials/detail-modal): dipakai halaman Agenda, Laporan, dll.
     * Semua nilai berupa teks biasa; modal menyisipkannya lewat textContent (aman XSS).
     */
    public function detailPayload(): array
    {
        $meta   = $this->typeMeta();
        $pic    = $this->room?->responsiblePic();
        $status = $this->statusText();
        $photo  = $this->photoUrl();

        return [
            'title'  => $this->event_name,
            'badges' => array_values(array_filter([
                ['text' => $this->category ?: $meta['label'], 'color' => $meta['color']],
                $this->is_outside_hours ? ['text' => 'Di luar jam sekolah', 'color' => '#6d4c41'] : null,
                ['text' => $status, 'color' => $status === 'Approved' ? '#2e7d32' : ($status === 'Rejected' ? '#c62828' : '#b8860b')],
            ])),
            'rows' => [
                ['Ruangan', ($this->room->room_name ?? '-') . ($this->room?->location ? ' (' . $this->room->location . ')' : '')],
                ['Tanggal', $this->date->copy()->locale('id')->translatedFormat('l, d F Y')],
                ['Waktu', $this->sessionLabel()],
                ['Pengaju', $this->user->fullname ?? $this->user->username ?? '-'],
                ['Penanggung jawab', $this->responsible_name],
                ['Tujuan penggunaan', $this->description],
                ['Keterangan', $this->notes],
                ['PIC ruangan', $pic ? ($pic->fullname ?: $pic->username) : null],
                ['Keterangan PIC', $pic?->pic_note],
                ['Alasan ditolak', $this->reject_reason],
                ['Dipindahkan', $this->move_reason],
            ],
            'photos' => $photo ? [['src' => $photo, 'caption' => 'Foto / dokumen izin']] : [],
        ];
    }
}
