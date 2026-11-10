# RAPI-Sekolah

**Ruang, Agenda, dan Pengaduan Informasi Sekolah** — aplikasi web untuk mengelola peminjaman ruangan, kalender agenda, dan pengaduan kerusakan fasilitas di sekolah (SMK Negeri 1 Bantul).

Dibangun dengan **Laravel 13** dan **PHP 8.3+**.

---

## Fitur Utama

### 1. Kalender Agenda Publik
- Halaman depan menampilkan kalender (FullCalendar) yang bisa dilihat **tanpa login**.
- Hanya agenda berstatus *approved* yang tampil.
- Libur nasional dan cuti bersama 2026–2027 sudah terpasang (SKB 3 Menteri). Sabtu, Minggu, dan tanggal merah diberi warna merah.
- Enam jenis event dengan warna berbeda: Acara Sekolah, Kegiatan Guru, Kegiatan Siswa, Hari Libur Sekolah, Hari Libur Nasional, dan Peminjaman Ruangan.
- Klik event untuk melihat detail dalam modal.

### 2. Peminjaman Ruangan
- Pengajuan berisi nama pengajuan, kategori, tujuan, keterangan, ruangan, tanggal, dan sesi.
- Waktu pinjam memakai **sesi jam pelajaran** (Senin–Kamis: 10 sesi, Jumat: 8 sesi), bukan jam bebas.
- **Validasi bentrok jadwal** otomatis. Agenda berstatus *pending* maupun *approved* sama-sama mengunci slot.
- Info ruangan kosong ditampilkan otomatis saat mengisi form.
- Lampiran dari galeri atau kamera (JPG/PNG/PDF).
- Status awal selalu `pending`; Waka/Admin yang menyetujui atau menolak (dengan alasan).
- Waka/Admin punya hak **veto pindah ruangan**.
- Peminjaman **di luar jam sekolah** hanya boleh untuk Guru, Waka, dan Admin. Siswa ditolak di backend.

### 3. Pengaduan Kerusakan
- Guru/Organisasi dan Waka/Admin dapat melaporkan kerusakan, lengkap dengan foto.
- Tiga status perbaikan dengan alur terkunci:

  ```
  Belum Diperbaiki → Menunggu Spare Part → Sudah Diperbaiki (final)
  Belum Diperbaiki → Sudah Diperbaiki
  ```
- PIC Ruang memperbarui status dan mengunggah foto hasil perbaikan.
- Setiap perubahan status tercatat di log (`complaint_status_logs`).
- Pengaduan yang melewati **48 jam** dan masih "Belum Diperbaiki" dianggap terlambat. Waka/Admin dapat mengirim **WA Warning** ke PIC lewat tautan `wa.me`.

### 4. Laporan Bulanan
- Rekap agenda/peminjaman dan pengaduan per bulan, dengan filter.
- Bisa dicetak, diunduh, atau dikirim ringkasannya lewat WhatsApp.

### 5. Akun & Keamanan
- Login berbasis username dan password.
- **Lupa password lewat OTP email**: kode berlaku 10 menit, maksimal 5 kali percobaan, kirim ulang setelah 60 detik, serta dibatasi rate limit.
- Sesi otomatis habis (session guard).
- Edit profil: nama, username, email, nomor HP, foto profil, dan keterangan PIC.
- Notifikasi dalam aplikasi; Waka/Admin dapat mengirim notifikasi ke PIC Ruang.

---

## Peran Pengguna (Role)

| Fitur | Publik | Siswa | Guru/Organisasi | PIC Ruang | Waka/Admin |
|---|:-:|:-:|:-:|:-:|:-:|
| Lihat kalender agenda (approved) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Ajukan peminjaman ruangan | – | ✅ | ✅ | – | ✅ |
| Pinjam di luar jam sekolah | – | ❌ | ✅ | – | ✅ |
| Buat pengaduan kerusakan | – | – | ✅ | – | ✅ |
| Ubah status pengaduan | – | – | – | ✅ | – |
| Setujui/tolak, pindah, edit, hapus agenda | – | – | – | – | ✅ |
| Kelola user, role, ruangan | – | – | – | – | ✅ |
| Laporan bulanan, WA Warning, kirim notifikasi | – | – | – | – | ✅ |

Empat role bawaan (`Waka/Admin`, `PIC Ruang`, `Guru/Organisasi`, `Siswa`) tidak dapat diubah namanya atau dihapus lewat menu Kelola Role.

---

## Teknologi

- **Backend:** PHP ^8.3, Laravel ^13.17
- **Database:** SQLite (default), bisa diganti MySQL lewat `.env`
- **Frontend:** Blade, CSS kustom (`public/css/`), Tailwind CSS 4 + Vite untuk build aset
- **Kalender:** FullCalendar 6.1.11 (CDN)
- **Testing:** PHPUnit 12

---

## Instalasi

### Prasyarat
- PHP 8.3 atau lebih baru (ekstensi umum Laravel + `pdo_sqlite`)
- Composer
- Node.js dan npm (untuk build aset)

### Langkah

```bash
# 1. Clone repository
git clone https://github.com/Rafasha21209/RAPI-SEKOLAH.git
cd RAPI-SEKOLAH

# 2. Pasang dependensi
composer install
npm install

# 3. Siapkan environment
cp .env.example .env
php artisan key:generate

# 4. Siapkan database (SQLite)
touch database/database.sqlite
php artisan migrate

# 5. Isi data awal (role, 30 ruangan, akun contoh)
php artisan db:seed

# 6. Tautkan storage agar foto dan avatar tampil
php artisan storage:link

# 7. Build aset dan jalankan
npm run build
php artisan serve
```

Buka **http://localhost:8000**.

Alternatif cepat: `composer setup` menjalankan sebagian besar langkah di atas (instal, `.env`, key, migrate, build aset).

### Konfigurasi `.env`

Pengaturan yang perlu disesuaikan:

```env
APP_NAME="RAPI-Sekolah"
APP_URL=http://localhost:8000
APP_LOCALE=id

DB_CONNECTION=sqlite

# Wajib diisi agar OTP lupa password terkirim.
# Dengan MAIL_MAILER=log, kode OTP hanya tertulis di storage/logs/laravel.log.
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=email-anda@gmail.com
MAIL_PASSWORD=app-password-anda
MAIL_FROM_ADDRESS="email-anda@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> **Zona waktu:** jadwal sesi memakai WIB, sedangkan `config/app.php` saat ini bernilai `UTC`. Ubah ke `Asia/Jakarta` jika jam tampil atau validasi jam luar sekolah terasa meleset.

---

## Akun Contoh (Seeder)

Setelah `php artisan db:seed`:

| Role | Username | Password |
|---|---|---|
| Waka/Admin | `admin` | `password` |
| PIC Ruang (semua ruangan) | `pic_ruang` | `password` |
| PIC Ruang (Wijaya Kusuma) | `pic_wijaya` | `password` |
| Guru/Organisasi | `guru` | `password` |
| Siswa | `siswa` | `password` |

> ⚠️ **Ganti semua password ini sebelum aplikasi dipakai secara nyata.**

Untuk uji coba fitur email dan OTP, tersedia `TestContactSeeder` (semua akun memakai satu inbox lewat *plus addressing* Gmail):

```bash
php artisan db:seed --class=TestContactSeeder
```

Seeder juga membuat 30 ruangan (Ruangan 1–30) dan ruang **Wijaya Kusuma** (kapasitas 200).

---

## Struktur Proyek

```
app/
├── Http/
│   ├── Controllers/    # Auth, Event, Complaint, Report, Room, User, Role, dll.
│   └── Middleware/     # CheckRole (RBAC per route)
├── Models/             # Event, Complaint, Room, User, Role, Notification, ...
├── Policies/           # EventPolicy, ComplaintPolicy
└── Support/Holidays.php
config/holidays.php     # Data libur nasional & cuti bersama
database/
├── migrations/
└── seeders/
public/                 # CSS, gambar, manifest.json (PWA)
resources/views/        # Blade: auth, events, complaints, reports, rooms, users, ...
routes/web.php
```

---

## Konfigurasi yang Bisa Diubah

| Yang ingin diubah | Lokasi |
|---|---|
| Jadwal jam pelajaran (Senin–Kamis / Jumat) | `app/Models/Event.php` → `SCHEDULE_REGULAR`, `SCHEDULE_FRIDAY` |
| Kategori pengajuan | `Event::CATEGORIES` |
| Warna dan label jenis event | `Event::TYPE_META` |
| Batas waktu penanganan pengaduan (48 jam) | `Complaint::REPAIR_DEADLINE_HOURS` |
| Libur tahun berikutnya | tambah baris di `config/holidays.php` |
| Masa berlaku OTP dan batas percobaan | `AuthController` (`OTP_TTL_MINUTES`, `OTP_MAX_ATTEMPTS`) |

Kalender, tanda merah, dan aturan "di luar jam sekolah" otomatis memakai data libur di `config/holidays.php`.

---

## Menjalankan Tes

```bash
composer test
```

---

## Catatan Keamanan

- **Jangan commit file `.env`** dan `database/database.sqlite` ke repository publik. Keduanya berisi kredensial dan data asli.
- Ganti seluruh password akun contoh sebelum produksi.
- Set `APP_ENV=production` dan `APP_DEBUG=false` saat di-deploy.

---

## Lisensi

Dibangun di atas [Laravel](https://laravel.com) yang berlisensi [MIT](https://opensource.org/licenses/MIT).
