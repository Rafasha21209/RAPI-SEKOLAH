# RAPI-Sekolah

Aplikasi web untuk **peminjaman ruangan**, **kalender agenda**, dan **pengaduan kerusakan** di sekolah (SMK Negeri 1 Bantul).

Dibuat dengan Laravel (PHP).

## Fitur

- Kalender agenda yang bisa dilihat siapa saja tanpa login
- Pengajuan peminjaman ruangan (dicek otomatis agar tidak bentrok jadwal)
- Pengaduan kerusakan fasilitas beserta foto dan status perbaikan
- Laporan bulanan
- Lupa password lewat kode OTP ke email
- 4 jenis pengguna: Waka/Admin, PIC Ruang, Guru/Organisasi, dan Siswa

## Cara Menjalankan

Pastikan sudah menginstal **PHP 8.3+**, **Composer**, dan **Node.js**.

```bash
git clone https://github.com/Rafasha21209/RAPI-SEKOLAH.git
cd RAPI-SEKOLAH

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan storage:link

npm run build
php artisan serve
```

Lalu buka http://localhost:8000

## Akun Contoh

| Peran | Username |
|---|---|
| Waka/Admin | admin |
| PIC Ruang | pic_ruang |
| Guru/Organisasi | guru |
| Siswa | siswa |

Ganti password ini jika aplikasi dipakai sungguhan.

## Teknologi

- Laravel 13, PHP 8.5.9
- SQLite
- Blade, Tailwind CSS, Vite
- FullCalendar
