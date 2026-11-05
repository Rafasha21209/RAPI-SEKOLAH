<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory;
    use SoftDeletes; // histori pengaduan tetap dapat ditelusuri

    /*
    |--------------------------------------------------------------------------
    | Konstanta status perbaikan (WAJIB hanya 3 nilai sesuai soal UKK)
    |--------------------------------------------------------------------------
    */
    public const STATUS_BELUM   = 'Belum Diperbaiki';
    public const STATUS_SPAREPART = 'Menunggu Spare Part';
    public const STATUS_SELESAI = 'Sudah Diperbaiki';

    /**
     * Daftar seluruh status yang sah.
     */
    public const STATUSES = [
        self::STATUS_BELUM,
        self::STATUS_SPAREPART,
        self::STATUS_SELESAI,
    ];

    /**
     * Alur transisi status yang diperbolehkan.
     * Belum Diperbaiki   -> Menunggu Spare Part | Sudah Diperbaiki
     * Menunggu Spare Part-> Sudah Diperbaiki
     * Sudah Diperbaiki   -> (final, tidak boleh diubah lagi)
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATUS_BELUM      => [self::STATUS_SPAREPART, self::STATUS_SELESAI],
        self::STATUS_SPAREPART  => [self::STATUS_SELESAI],
        self::STATUS_SELESAI    => [],
    ];

    /**
     * Batas waktu penanganan (dalam jam). Lewat dari ini dan status masih
     * "Belum Diperbaiki" => pengaduan dianggap terlambat (overdue) sehingga
     * Waka/Admin boleh mengirim WA Warning ke PIC ruangan.
     */
    public const REPAIR_DEADLINE_HOURS = 48;

    protected $fillable = [
        'room_id',
        'event_id',
        'reporter_id',
        'pic_id',
        'description',
        'photo_path',
        'repair_photo_path',
        'status_repair',
        'handler_id',
        'reported_at',
        'repaired_at',
        'last_warned_at',
        'warning_count',
    ];

    protected $casts = [
        'reported_at'    => 'datetime',
        'repaired_at'    => 'datetime',
        'last_warned_at' => 'datetime',
        'warning_count'  => 'integer',
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

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /** PIC yang ditetapkan otomatis saat laporan dibuat (snapshot rooms.pic_id). */
    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /**
     * Histori perubahan status / pengiriman warning.
     */
    public function statusLogs()
    {
        return $this->hasMany(ComplaintStatusLog::class, 'complaint_id')->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scope
    |--------------------------------------------------------------------------
    */
    /**
     * Batasi hasil query hanya pada ruangan yang menjadi tanggung jawab PIC.
     * Satu PIC bisa memegang beberapa ruangan -> semua ikut tampil.
     */
    public function scopeForPic(Builder $query, int $picId): Builder
    {
        return $query->whereHas('room', function ($q) use ($picId) {
            $q->where('pic_id', $picId);
        });
    }

    public function scopeBelumSelesai(Builder $query): Builder
    {
        return $query->where('status_repair', '!=', self::STATUS_SELESAI);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper logika status
    |--------------------------------------------------------------------------
    */
    /**
     * Status tujuan yang sah dari status saat ini.
     */
    public function allowedNextStatuses(): array
    {
        return self::ALLOWED_TRANSITIONS[$this->status_repair] ?? [];
    }

    public function canTransitionTo(?string $status): bool
    {
        return $status !== null && in_array($status, $this->allowedNextStatuses(), true);
    }

    public function isSelesai(): bool
    {
        return $this->status_repair === self::STATUS_SELESAI;
    }

    /**
     * Batas waktu penanganan berdasarkan waktu lapor.
     */
    public function repairDeadline()
    {
        $reportedAt = $this->reported_at ?? $this->created_at;

        return $reportedAt ? $reportedAt->copy()->addHours(self::REPAIR_DEADLINE_HOURS) : null;
    }

    /**
     * Pengaduan terlambat: masih "Belum Diperbaiki" DAN sudah melewati batas waktu.
     */
    public function isOverdue(): bool
    {
        if ($this->status_repair !== self::STATUS_BELUM) {
            return false;
        }

        $deadline = $this->repairDeadline();

        return $deadline !== null && now()->greaterThan($deadline);
    }

    /**
     * Syarat tombol "Kirim WA Warning" boleh aktif:
     * status Belum Diperbaiki + sudah lewat batas waktu + PIC ruangan punya nomor WA.
     */
    public function canReceiveWaWarning(): bool
    {
        return $this->isOverdue()
            && $this->room
            && $this->room->responsiblePic()
            && !empty($this->room->responsiblePic()->phone_number);
    }
}
