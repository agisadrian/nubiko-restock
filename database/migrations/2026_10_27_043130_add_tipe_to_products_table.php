<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'tipe')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('tipe')->default('satuan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'tipe')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('tipe');
            });
        }
    }
};