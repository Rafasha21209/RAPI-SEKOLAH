<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     * Menambahkan kolom photo_path di tabel events untuk menyimpan
     * foto izin/bukti persetujuan peminjaman ruangan.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('move_reason');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
