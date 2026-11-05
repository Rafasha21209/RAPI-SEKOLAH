<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // 1. Cek apakah user sudah login
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
        }

        // 2. Ambil nama role dari user yang sedang login
        $userRole = Auth::user()->role->role_name;

        // 3. Cek apakah role user sesuai dengan yang diizinkan untuk mengakses halaman ini
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        // 4. Jika role tidak sesuai, tampilkan pesan error 403 (Forbidden)
        abort(403, 'Anda tidak memiliki hak akses untuk membuka halaman ini.');
    }
}