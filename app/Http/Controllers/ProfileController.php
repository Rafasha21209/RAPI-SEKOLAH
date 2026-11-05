<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

/**
 * Profil user yang sedang login (hanya lihat): nama, username, role, email, nomor HP.
 */
class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user()->load('role');

        return view('profile.show', compact('user'));
    }
}
