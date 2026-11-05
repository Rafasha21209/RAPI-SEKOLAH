<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     * Tabel `roles` (lihat migrasi create_roles_table) tidak punya kolom
     * created_at / updated_at. Tanpa baris ini, Role::create() dan
     * $role->update() mencoba menulis kolom tersebut sehingga query gagal
     * ("Unknown column 'updated_at'") dan Tambah/Edit Role error 500.
     */
    public $timestamps = false;

    /**
     * Role bawaan sistem. Namanya dipakai langsung di middleware route
     * (role:Waka/Admin, dst.) dan di policy, jadi tidak boleh diubah
     * namanya atau dihapus lewat menu Kelola Role.
     */
    public const PROTECTED_ROLES = [
        'Waka/Admin',
        'PIC Ruang',
        'Guru/Organisasi',
    ];

    protected $fillable = [
        'role_name',
    ];

    public function isProtected(): bool
    {
        return in_array($this->role_name, self::PROTECTED_ROLES, true);
    }

    /**
     * Semua user yang memakai role ini.
     * Dipakai RoleController untuk mencegah penghapusan role yang masih
     * dipakai user (supaya tidak ada user "kehilangan" role).
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
