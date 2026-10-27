<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('restocks', 'sku')) {
            Schema::table('restocks', function (Blueprint $table) {
                $table->string('sku')->nullable()->index();
            });
        }

        // Isi SKU data lama dari Master Produk berdasarkan nama produk
        DB::statement('UPDATE restocks SET sku = products.sku FROM products WHERE restocks.nama_produk = products.nama_produk AND restocks.sku IS NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('restocks', 'sku')) {
            Schema::table('restocks', function (Blueprint $table) {
                $table->dropColumn('sku');
            });
        }
    }
};