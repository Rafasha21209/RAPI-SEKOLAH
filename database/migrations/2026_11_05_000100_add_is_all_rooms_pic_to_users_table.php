<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.is_all_rooms_pic : PIC yang bertanggung jawab atas SEMUA ruangan.
 * PIC lain (is_all_rooms_pic = false) hanya memegang ruangan yang pic_id-nya dirinya
 * (mis. PIC khusus ruang Wijaya Kusuma).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_all_rooms_pic')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_all_rooms_pic')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Sengaja kosong supaya data tidak hilang saat rollback.
    }
};
