<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom pelengkap pada tabel complaints TANPA mengubah
 * kolom/nama field yang sudah ada (room_id, reporter_id, description,
 * photo_path, status_repair, handler_id, reported_at).
 *
 * - repaired_at     : waktu status berubah menjadi "Sudah Diperbaiki".
 * - last_warned_at  : waktu terakhir Waka/Admin mengirim WA Warning ke PIC.
 * - warning_count   : jumlah WA Warning yang sudah dikirim.
 * - deleted_at      : soft delete, agar histori pengaduan tetap dapat ditelusuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'repaired_at')) {
                $table->timestamp('repaired_at')->nullable()->after('status_repair');
            }
            if (!Schema::hasColumn('complaints', 'last_warned_at')) {
                $table->timestamp('last_warned_at')->nullable()->after('reported_at');
            }
            if (!Schema::hasColumn('complaints', 'warning_count')) {
                $table->unsignedInteger('warning_count')->default(0)->after('last_warned_at');
            }
            if (!Schema::hasColumn('complaints', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            foreach (['repaired_at', 'last_warned_at', 'warning_count'] as $column) {
                if (Schema::hasColumn('complaints', $column)) {
                    $table->dropColumn($column);
                }
            }
            if (Schema::hasColumn('complaints', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
