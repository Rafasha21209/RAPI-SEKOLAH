<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - users.email        : tujuan pengiriman kode OTP lupa password (tidak unik: saat uji coba
 *                        semua role boleh memakai satu email).
 * - password_otps      : kode OTP (disimpan ter-hash), masa berlaku, dan jumlah percobaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('email', 150)->nullable();
            });
        }

        if (!Schema::hasTable('password_otps')) {
            Schema::create('password_otps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Sengaja kosong supaya data tidak hilang saat rollback.
    }
};
