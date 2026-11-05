<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * MANAJEMEN USER & ASSIGN ROLE (khusus Waka/Admin)
 *
 * Akses dibatasi lewat middleware 'role:Waka/Admin' di routes/web.php.
 */
class UserController extends Controller
{
    /**
     * Daftar seluruh user beserta role-nya saat ini + form ganti role.
     */
    public function index()
    {
        $users = User::with('role')->orderBy('fullname')->get();
        $roles = Role::orderBy('role_name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Admin menambahkan user baru: mengisi nama sendiri, username, password,
     * nomor HP (opsional), dan memilih role.
     *
     * Password di-hash di sini karena model User tidak memakai cast "hashed";
     * tanpa Hash::make() user baru tidak akan bisa login (Auth::attempt
     * membandingkan dengan hash).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fullname'     => ['required', 'string', 'max:100'],
            'username'     => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password'     => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'email'        => ['nullable', 'email:rfc', 'max:150'],
            'role_id'      => ['required', 'integer', Rule::exists('roles', 'id')],
            'is_all_rooms_pic' => ['nullable', 'boolean'],
        ], [
            'fullname.required'     => 'Nama lengkap wajib diisi.',
            'fullname.max'          => 'Nama lengkap maksimal 100 karakter.',
            'username.required'     => 'Username wajib diisi.',
            'username.unique'       => 'Username tersebut sudah dipakai user lain.',
            'username.regex'        => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan strip (tanpa spasi).',
            'password.required'     => 'Password wajib diisi.',
            'password.min'          => 'Password minimal 8 karakter.',
            'password.confirmed'    => 'Konfirmasi password tidak sama.',
            'phone_number.regex'    => 'Nomor HP hanya boleh berisi angka, spasi, tanda + atau -.',
            'phone_number.max'      => 'Nomor HP maksimal 20 karakter.',
            'email.email'           => 'Format email tidak valid.',
            'role_id.required'      => 'Role wajib dipilih.',
            'role_id.exists'        => 'Role yang dipilih tidak valid.',
        ]);

        $user = User::create([
            'fullname'     => trim($validated['fullname']),
            'username'     => $validated['username'],
            'password'     => Hash::make($validated['password']),
            'phone_number' => $validated['phone_number'] ?? null,
            'email'        => $validated['email'] ?? null,
            'role_id'      => (int) $validated['role_id'],
            'is_all_rooms_pic' => $request->boolean('is_all_rooms_pic'),
        ]);

        $user->load('role');

        return redirect()
            ->route('users.index')
            ->with('success', 'User "' . $user->fullname . '" (' . $user->username . ') berhasil ditambahkan sebagai ' . ($user->role->role_name ?? '-') . '.');
    }

    /**
     * Assign / mengganti role seorang user.
     */
    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ], [
            'role_id.required' => 'Role wajib dipilih.',
            'role_id.exists'   => 'Role yang dipilih tidak valid.',
        ]);

        $rolenya = (int) $validated['role_id'];

        // Proteksi anti-lockout: jangan sampai admin mengganti role akun
        // sendiri sehingga tidak ada lagi Waka/Admin yang bisa mengelola sistem.
        if ((int) $user->id === (int) Auth::id() && $rolenya !== (int) $user->role_id) {
            $masihAdaAdminLain = User::where('id', '!=', $user->id)
                ->whereHas('role', fn ($q) => $q->where('role_name', 'Waka/Admin'))
                ->exists();

            if (!$masihAdaAdminLain) {
                return back()->with(
                    'error',
                    'Tidak bisa mengubah role akun Anda sendiri karena Anda satu-satunya Waka/Admin yang tersisa di sistem.'
                );
            }
        }

        $user->update(['role_id' => $rolenya]);

        return back()->with('success', 'Role user "' . ($user->fullname ?? $user->username) . '" berhasil diperbarui.');
    }

    /**
     * Tandai / lepas PIC sebagai "PIC semua ruangan".
     * PIC lain tetap hanya memegang ruangan yang ditunjuk di Kelola Ruangan.
     */
    public function updatePicScope(Request $request, User $user)
    {
        if (!$user->isPic()) {
            return back()->with('error', 'Hanya user dengan role PIC Ruang yang bisa dijadikan PIC semua ruangan.');
        }

        $user->update(['is_all_rooms_pic' => $request->boolean('is_all_rooms_pic')]);

        return back()->with('success', 'Cakupan PIC "' . ($user->fullname ?? $user->username) . '" diperbarui: '
            . ($user->is_all_rooms_pic ? 'semua ruangan.' : 'hanya ruangan yang ditunjuk.'));
    }
}
