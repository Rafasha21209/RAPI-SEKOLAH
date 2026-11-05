<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * KHUSUS UJI COBA.
 * - Semua user memakai satu nomor HP.
 * - Email tiap user BERBEDA tetapi masuk ke SATU inbox, memakai "plus addressing" Gmail:
 *     rafdan543+<username>@gmail.com  ->  tetap diterima di rafdan543@gmail.com
 *   Jadi tiap akun/role punya email unik (syarat lupa password), tanpa perlu banyak akun Gmail.
 *
 * Jalankan:  php artisan db:seed --class=TestContactSeeder
 */
class TestContactSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan akun uji coba Guru dan Organisasi ada (role "Guru/Organisasi", id 3).
        foreach ([
            'guru'      => 'Guru Uji Coba',
            'organisasi' => 'Organisasi Uji Coba',
        ] as $username => $fullname) {
            User::firstOrCreate(
                ['username' => $username],
                [
                    'password' => Hash::make('password'),
                    'fullname' => $fullname,
                    'role_id'  => 3,
                ]
            );
        }

        foreach (User::with('role')->get() as $user) {
            $slug = preg_replace('/[^a-z0-9._-]/', '', mb_strtolower($user->username));

            $user->update([
                'phone_number' => '083130018103',
                'email'        => 'rafdan543+' . $slug . '@gmail.com',
            ]);
        }
    }
}
