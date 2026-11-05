<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('public.calendar');
})->name('public.calendar');

Route::get('/calendar', function () {
    return view('public.calendar');
})->name('calendar');

// KETENTUAN: Endpoint JSON untuk FullCalendar, hanya agenda "approved".
// Publik (tanpa login) karena tujuannya memang kalender transparan untuk umum.
Route::get('/calendar/feed', [EventController::class, 'calendarFeed'])->name('calendar.feed');

// Autentikasi (Guest Only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Lupa password: username -> kode OTP dikirim ke email -> OTP + password baru.
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/forgot-password/verify', [AuthController::class, 'showVerifyOtp'])->name('password.verify');
    Route::post('/forgot-password/resend', [AuthController::class, 'resendOtp'])
        ->middleware('throttle:3,1')
        ->name('password.resend');
    Route::post('/forgot-password/verify', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,1')
        ->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Wajib Login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Notifikasi milik user yang login
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Halaman list bersama (Read-only / index) - semua role yang login bisa lihat
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');

    // ==========================================
    // AGENDA / PEMINJAMAN RUANGAN
    // ==========================================
    // Guru/Organisasi & Waka/Admin: boleh mengajukan peminjaman ruangan
    // (status awal dipaksa "pending" di controller, wajib approval).
    Route::middleware('role:Guru/Organisasi,Waka/Admin')->group(function () {
        Route::post('/events', [EventController::class, 'store'])->name('events.store');
    });

    // Hanya Waka/Admin: approve/reject, veto pindah ruang, edit, hapus agenda
    Route::middleware('role:Waka/Admin')->group(function () {
        Route::put('/events/{id}/status', [EventController::class, 'updateApproval'])->name('events.status');
        Route::put('/events/{id}/move', [EventController::class, 'moveRoom'])->name('events.move');
        Route::put('/events/{id}', [EventController::class, 'update'])->name('events.update');
        Route::delete('/events/{id}', [EventController::class, 'destroy'])->name('events.destroy');
    });

    // ==========================================
    // PENGADUAN KERUSAKAN
    // ==========================================
    Route::middleware('role:Guru/Organisasi,Waka/Admin')->group(function () {
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    });

    Route::middleware('role:PIC Ruang')->group(function () {
        Route::put('/complaints/{id}/status', [ComplaintController::class, 'updateStatus'])->name('complaints.update-status');
    });

    Route::middleware('role:Waka/Admin')->group(function () {
        Route::get('/complaints/{id}/wa-warning', [ComplaintController::class, 'sendWaWarning'])->name('complaints.wa-warning');
        Route::delete('/complaints/{id}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');
    });

    // ==========================================
    // MANAJEMEN ROLE & USER (khusus Waka/Admin)
    // ==========================================
    Route::middleware('role:Waka/Admin')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        // Kelola Ruangan (tambah ruangan kelas, edit, aktif/nonaktif, hapus)
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::put('/rooms/{room}/toggle', [RoomController::class, 'toggle'])->name('rooms.toggle');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

        // Laporan bulanan (agenda/peminjaman + pengaduan): tampil, cetak, unduh, kirim WA
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}/pic-scope', [UserController::class, 'updatePicScope'])->name('users.update-pic-scope');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
    });
});
