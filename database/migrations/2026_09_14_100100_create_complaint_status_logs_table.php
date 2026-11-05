<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel histori (audit trail) modul pengaduan.
 *
 * Dibuat karena syarat UKK: "Jangan menghapus data pengaduan lama ketika
 * status berubah. Simpan histori/data yang diperlukan agar perubahan tetap
 * dapat ditelusuri."
 *
 * Tabel ini HANYA menambah, tidak mengubah tabel complaints yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->onDelete('cascade');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            // 'status_change' = PIC mengubah status, 'wa_warning' = Waka/Admin kirim peringatan
            $table->string('action', 30)->default('status_change');
            $table->string('status_from', 30)->nullable();
            $table->string('status_to', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_status_logs');
    }
};
