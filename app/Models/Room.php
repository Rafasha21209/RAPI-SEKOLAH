<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    public const TYPES = ['Kelas', 'Laboratorium', 'Perpustakaan', 'Lapangan', 'Aula', 'Lainnya'];

    protected $fillable = [
        'room_name',
        'room_type',
        'capacity',
        'computer_count',
        'location',
        'pic_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Keterangan singkat: "untuk 36 siswa", "36 komputer · untuk 36 siswa", "kapasitas 150 orang". */
    public function infoText(): string
    {
        $unit  = in_array($this->room_type, ['Kelas', 'Laboratorium'], true) ? 'untuk %d siswa' : 'kapasitas %d orang';
        $parts = [];

        if ($this->computer_count) {
            $parts[] = $this->computer_count . ' komputer';
        }
        if ($this->capacity) {
            $parts[] = sprintf($unit, $this->capacity);
        }

        return implode(', ', $parts);
    }

    /** Teks pada pilihan ruangan: "Ruangan 1 — untuk 36 siswa". */
    public function optionLabel(): string
    {
        $info = $this->infoText();

        return $info !== '' ? $this->room_name . ' — ' . $info : $this->room_name;
    }

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

    /** Keterangan PIC penanggung jawab ruangan ini (tugas/tanggung jawab/catatan), atau null. */
    public function picNote(): ?string
    {
        $note = trim((string) ($this->responsiblePic()?->pic_note ?? ''));

        return $note !== '' ? $note : null;
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