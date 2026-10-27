<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stock_outs', 'sku')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $table->string('sku')->nullable()->index();
            });
        }

        // Isi SKU data lama dari Master Produk berdasarkan nama produk
        DB::statement('UPDATE stock_outs SET sku = products.sku FROM products WHERE stock_outs.nama_produk = products.nama_produk AND stock_outs.sku IS NULL');
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_outs', 'sku')) {
            Schema::table('stock_outs', function (Blueprint $table) {
                $table->dropColumn('sku');
            });
        }
    }
};