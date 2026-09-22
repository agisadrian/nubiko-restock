<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restocks', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_kirim');
            $table->string('kota_asal_gudang');
            $table->string('nama_produk');
            $table->integer('qty');
            $table->enum('status', ['sudah', 'belum'])->default('belum');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restocks');
    }
};