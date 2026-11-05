<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memastikan tabel `events` punya seluruh kolom yang dibutuhkan modul
 * Agenda/Peminjaman Ruangan. Ditulis idempoten (cek Schema::hasTable /
 * hasColumn dulu) supaya aman dijalankan di database yang tabel
 * events-nya sudah ada dari versi project sebelumnya, maupun yang
 * belum punya tabel ini sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('event_name');
                $table->date('date');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('status', 20)->default('pending'); // pending | approved | rejected
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->string('move_reason')->nullable();
                $table->string('photo_path')->nullable();
                $table->timestamps();
            });

            return;
        }

        // Tabel sudah ada dari versi sebelumnya -> tambah kolom yang kurang saja.
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('room_id')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('events', 'status')) {
                $table->string('status', 20)->default('pending')->after('end_time');
            }
            if (!Schema::hasColumn('events', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('events', 'move_reason')) {
                $table->string('move_reason')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('events', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('move_reason');
            }
        });

        // Kalau skema lama masih pakai pic_id untuk peminjam, salin datanya
        // ke user_id (tanpa menghapus pic_id, non-destruktif).
        if (Schema::hasColumn('events', 'pic_id') && Schema::hasColumn('events', 'user_id')) {
            DB::table('events')->whereNull('user_id')->update(['user_id' => DB::raw('pic_id')]);
        }
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun di down() supaya tidak
        // menghilangkan data agenda yang sudah ada kalau di-rollback.
    }
};
