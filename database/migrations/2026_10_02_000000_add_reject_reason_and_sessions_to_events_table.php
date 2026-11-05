<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - reject_reason  : alasan penolakan peminjaman (diisi Waka/Admin saat "Tolak").
 * - start_session / end_session : waktu pinjam berbasis SESI 1-10 (menggantikan jam).
 * - start_time / end_time dijadikan nullable agar data lama (berbasis jam) tetap
 *   utuh, sedangkan pengajuan baru hanya memakai sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'reject_reason')) {
                $table->text('reject_reason')->nullable();
            }
            if (!Schema::hasColumn('events', 'start_session')) {
                $table->unsignedTinyInteger('start_session')->nullable();
            }
            if (!Schema::hasColumn('events', 'end_session')) {
                $table->unsignedTinyInteger('end_session')->nullable();
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Sengaja kosong supaya data agenda tidak hilang saat rollback.
    }
};
