<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'username',
        'password',
        'fullname',
        'phone_number',
        'email',
        'role_id',
        'is_all_rooms_pic',
    ];

    protected $casts = [
        'is_all_rooms_pic' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'pic_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'user_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper role (dipakai untuk RBAC di backend)
    |--------------------------------------------------------------------------
    */
    public function roleName(): string
    {
        return $this->role->role_name ?? '';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleName(), $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Waka/Admin');
    }

    public function isPic(): bool
    {
        return $this->hasRole('PIC Ruang');
    }

    /** PIC yang memegang semua ruangan. */
    public function isAllRoomsPic(): bool
    {
        return $this->isPic() && (bool) $this->is_all_rooms_pic;
    }

    public function isReporter(): bool
    {
        return $this->hasRole('Guru/Organisasi');
    }
}