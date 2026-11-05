<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MANAJEMEN ROLE (khusus Waka/Admin)
 *
 * Akses dibatasi lewat middleware 'role:Waka/Admin' di routes/web.php,
 * bukan hanya disembunyikan di view.
 */
class RoleController extends Controller
{
    /**
     * Daftar seluruh role beserta jumlah user yang memakainya.
     */
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('id')->get();

        return view('roles.index', compact('roles'));
    }

    /**
     * Menambahkan role baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:50', Rule::unique('roles', 'role_name')],
        ], [
            'role_name.required' => 'Nama role wajib diisi.',
            'role_name.unique'   => 'Nama role tersebut sudah ada.',
        ]);

        Role::create($validated);

        return back()->with('success', 'Role "' . $validated['role_name'] . '" berhasil ditambahkan.');
    }

    /**
     * Mengubah nama role yang sudah ada.
     */
    public function update(Request $request, Role $role)
    {
        if ($role->isProtected()) {
            return back()->with(
                'error',
                'Role "' . $role->role_name . '" adalah role bawaan sistem dan namanya tidak bisa diubah.'
            );
        }

        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:50', Rule::unique('roles', 'role_name')->ignore($role->id)],
        ], [
            'role_name.required' => 'Nama role wajib diisi.',
            'role_name.unique'   => 'Nama role tersebut sudah ada.',
        ]);

        $role->update($validated);

        return back()->with('success', 'Role berhasil diperbarui menjadi "' . $validated['role_name'] . '".');
    }

    /**
     * Menghapus role.
     * Dicegah jika masih ada user yang memakai role tersebut, supaya
     * tidak ada user yang tiba-tiba kehilangan role (role_id jadi rusak).
     */
    public function destroy(Role $role)
    {
        if ($role->isProtected()) {
            return back()->with(
                'error',
                'Role "' . $role->role_name . '" adalah role bawaan sistem dan tidak bisa dihapus.'
            );
        }

        $jumlahUser = $role->users()->count();

        if ($jumlahUser > 0) {
            return back()->with(
                'error',
                'Role "' . $role->role_name . '" masih dipakai oleh ' . $jumlahUser
                    . ' user, tidak bisa dihapus. Pindahkan user tersebut ke role lain dulu lewat menu Kelola User.'
            );
        }

        $namaRole = $role->role_name;
        $role->delete();

        return back()->with('success', 'Role "' . $namaRole . '" berhasil dihapus.');
    }
}
