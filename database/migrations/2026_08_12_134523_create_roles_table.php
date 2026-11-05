<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name', 50);
        });

        // Insert data awal role
        DB::table('roles')->insert([
            ['id' => 1, 'role_name' => 'Waka/Admin'],
            ['id' => 2, 'role_name' => 'PIC Ruang'],
            ['id' => 3, 'role_name' => 'Guru/Organisasi'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};