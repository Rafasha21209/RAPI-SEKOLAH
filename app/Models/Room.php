<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    public const TYPES = ['Kelas', 'Laboratorium', 'Perpustakaan', 'Lainnya'];

    protected $fillable = [
        'room_name',
        'room_type',
        'capacity',
        'location',
        'pic_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pic()
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'room_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'room_id');
    }

    /**
     * Apakah ruangan ini menjadi tanggung jawab user tertentu (PIC)?
     * Dipakai untuk authorization di backend agar PIC tidak bisa
     * memanipulasi room_id / complaint_id milik ruangan orang lain.
     */
    public function isManagedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // PIC semua ruangan boleh menangani ruangan mana pun.
        if ($user->isAllRoomsPic()) {
            return true;
        }

        return $this->pic_id !== null && (int) $this->pic_id === (int) $user->id;
    }

    /**
     * PIC penanggung jawab otomatis: PIC khusus ruangan (mis. Wijaya Kusuma);
     * jika ruangan tidak punya PIC khusus, dipakai PIC semua ruangan.
     */
    public function responsiblePic(): ?User
    {
        return $this->pic ?? User::where('is_all_rooms_pic', true)
            ->whereHas('role', fn ($q) => $q->where('role_name', 'PIC Ruang'))
            ->orderBy('id')
            ->first();
    }

    /** Semua PIC yang perlu diberi tahu untuk ruangan ini (khusus + semua ruangan). */
    public function notifiablePicIds(): array
    {
        $general = User::where('is_all_rooms_pic', true)
            ->whereHas('role', fn ($q) => $q->where('role_name', 'PIC Ruang'))
            ->pluck('id')->all();

        return array_values(array_unique(array_filter(array_merge($general, [$this->pic_id]))));
    }
}