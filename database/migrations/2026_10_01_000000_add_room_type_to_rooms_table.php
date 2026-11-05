<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('rooms', 'room_type')) {
            Schema::table('rooms', function (Blueprint $table) {
                // Jenis ruangan; ruangan lama otomatis dianggap "Kelas".
                $table->string('room_type', 30)->default('Kelas')->after('room_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rooms', 'room_type')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropColumn('room_type');
            });
        }
    }
};
