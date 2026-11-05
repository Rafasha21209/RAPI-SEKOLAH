<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // Ambil data role user dari berbagai kemungkinan struktur database
        $userRole = null;
        if ($user) {
            if (isset($user->role)) {
                if (is_object($user->role)) {
                    $userRole = $user->role->role_name ?? $user->role->name ?? $user->role->nama_role ?? null;
                } else {
                    $userRole = $user->role;
                }
            } elseif (isset($user->role_name)) {
                $userRole = $user->role_name;
            }
        }

        // Normalisasi string (ubah ke huruf kecil semua dan hapus spasi berlebih)
        $normalizedUserRole = strtolower(trim((string) $userRole));
        
        $normalizedAllowedRoles = array_map(function ($role) {
            return strtolower(trim($role));
        }, $roles);

        // Cek apakah role user cocok dengan salah satu role yang diizinkan di route
        if (in_array($normalizedUserRole, $normalizedAllowedRoles)) {
            return $next($request);
        }

        // Jika masih tidak cocok, tampilkan pesan error 403 beserta info debugnya agar jelas
        abort(403, 'Akses ditolak. Role akun anda terdeteksi: "' . ($userRole ?? 'Kosong') . '", sementara route ini membutuhkan: [' . implode(', ', $roles) . ']');
    }
}