<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - event_id          : peminjaman ruang yang menjadi asal laporan kerusakan (nullable,
 *                       laporan lama / laporan manual tetap valid).
 * - pic_id            : PIC ruangan yang otomatis ditetapkan saat laporan dibuat
 *                       (salinan rooms.pic_id, agar histori tidak berubah bila PIC ruangan diganti).
 * - repair_photo_path : foto bukti perbaikan, wajib diunggah PIC saat status "Sudah Diperbaiki".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'event_id')) {
                $table->foreignId('event_id')->nullable()->after('room_id')
                    ->constrained('events')->nullOnDelete();
            }
            if (!Schema::hasColumn('complaints', 'pic_id')) {
                $table->foreignId('pic_id')->nullable()->after('reporter_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('complaints', 'repair_photo_path')) {
                $table->string('repair_photo_path')->nullable()->after('photo_path');
            }
        });
    }

    public function down(): void
    {
        // Sengaja kosong supaya data tidak hilang saat rollback.
    }
};
