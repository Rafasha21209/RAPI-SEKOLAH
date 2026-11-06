<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\Complaint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Roles terlebih dahulu (Agar role_id di User valid)
        $roles = [
            ['id' => 1, 'role_name' => 'Waka/Admin'],
            ['id' => 2, 'role_name' => 'PIC Ruang'],
            ['id' => 3, 'role_name' => 'Guru/Organisasi'],
            ['id' => 4, 'role_name' => 'Siswa'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }

       // Generate Ruangan 1 sampai 30
            for ($i = 1; $i <= 30; $i++) {
            Room::updateOrCreate(
             ['room_name' => 'Ruangan ' . $i],
             [
                 'location' => 'Gedung Utama',
            'capacity' => 36, // <--- Tambahkan nilai kapasitas di sini
            ]
        );
    }
        // 3. Seed Users
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('password'),
                'fullname' => 'Waka/Admin Sekolah',
                'phone_number' => '081234567890',
                'role_id' => 1,
            ]
        );

        User::updateOrCreate(
            ['username' => 'pic_ruang'],
            [
                'password' => Hash::make('password'),
                'fullname' => 'Petugas PIC Lab',
                'phone_number' => '081234567891',
                'role_id' => 2,
            ]
        );

        User::updateOrCreate(
            ['username' => 'guru'],
            [
                'password' => Hash::make('password'),
                'fullname' => 'Guru Pengajar',
                'phone_number' => '081234567892',
                'role_id' => 3,
            ]
        );

        User::updateOrCreate(
            ['username' => 'siswa'],
            [
                'password' => Hash::make('password'),
                'fullname' => 'Siswa Contoh',
                'phone_number' => '081234567894',
                'role_id' => 4,
            ]
        );

        // 3b. DUA PIC:
        //   - pic_ruang  : PIC SEMUA ruangan (is_all_rooms_pic = true)
        //   - pic_wijaya : PIC khusus ruang Wijaya Kusuma
        User::where('username', 'pic_ruang')->update(['is_all_rooms_pic' => true]);

        User::updateOrCreate(
            ['username' => 'pic_wijaya'],
            [
                'password' => Hash::make('password'),
                'fullname' => 'Petugas PIC Wijaya Kusuma',
                'phone_number' => '081234567893',
                'role_id' => 2,
            ]
        );

        $picWijaya = User::where('username', 'pic_wijaya')->first();

        Room::updateOrCreate(
            ['room_name' => 'Wijaya Kusuma'],
            [
                'room_type' => 'Lainnya',
                'location'  => 'Gedung Utama',
                'capacity'  => 200,
                'pic_id'    => $picWijaya->id,
            ]
        );

        // 4. Seed Dummy Complaint (Data Pengaduan)
        $guru = User::where('username', 'guru')->first();

        Complaint::firstOrCreate(
            [
                'room_id' => 1,
                'description' => 'Proyektor mati total saat KBM',
            ],
            [
                'reporter_id' => $guru->id ?? 3,
                'status_repair' => Complaint::STATUS_BELUM,
                'reported_at' => now(),
            ]
        );

        // Dummy pengaduan yang sudah MELEWATI batas waktu penanganan,
        // untuk menguji tombol "Kirim WA Warning" milik Waka/Admin.
        Complaint::firstOrCreate(
            [
                'room_id' => 2,
                'description' => 'AC ruangan bocor dan tidak dingin sejak pekan lalu',
            ],
            [
                'reporter_id' => $guru->id ?? 3,
                'status_repair' => Complaint::STATUS_BELUM,
                'reported_at' => now()->subHours(Complaint::REPAIR_DEADLINE_HOURS + 6),
            ]
        );
    }
}