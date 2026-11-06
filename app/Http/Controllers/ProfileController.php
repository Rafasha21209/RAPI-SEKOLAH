<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Profil user yang sedang login: lihat & edit (nama, username, email, nomor HP, foto profil,
 * dan keterangan PIC bila role PIC Ruang). Role & password tidak diubah di sini
 * (role oleh Waka/Admin, password lewat "Lupa Password").
 */
class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user()->load('role');

        return view('profile.show', compact('user'));
    }

    public function edit()
    {
        $user = Auth::user()->load('role');

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'fullname'     => ['required', 'string', 'max:100'],
            'username'     => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email'        => ['nullable', 'email:rfc', 'max:150'],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'avatar'       => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];

        if ($user->isPic()) {
            $rules['pic_note'] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules, [
            'fullname.required'  => 'Nama lengkap wajib diisi.',
            'username.required'  => 'Nama pengguna wajib diisi.',
            'username.unique'    => 'Nama pengguna tersebut sudah dipakai user lain.',
            'username.regex'     => 'Nama pengguna hanya boleh berisi huruf, angka, titik, garis bawah, dan strip (tanpa spasi).',
            'email.email'        => 'Format email tidak valid.',
            'phone_number.regex' => 'Nomor HP hanya boleh berisi angka, spasi, tanda + atau -.',
            'avatar.image'       => 'Foto profil harus berupa gambar.',
            'avatar.mimes'       => 'Foto profil harus berformat JPG atau PNG.',
            'avatar.max'         => 'Ukuran foto profil maksimal 2 MB.',
            'pic_note.max'       => 'Keterangan maksimal 500 karakter.',
        ]);

        $data = [
            'fullname'     => trim($validated['fullname']),
            'username'     => $validated['username'],
            'email'        => ($validated['email'] ?? null) ?: null,
            'phone_number' => ($validated['phone_number'] ?? null) ?: null,
        ];

        if ($user->isPic()) {
            $data['pic_note'] = isset($validated['pic_note']) ? (trim($validated['pic_note']) ?: null) : null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->boolean('remove_avatar') && $user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = null;
        }

        $user->update($data);

        return redirect()->route('profile.show')->with('success', 'Profil berhasil diperbarui.');
    }
}
