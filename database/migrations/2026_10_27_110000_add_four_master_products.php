<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahan 4 produk master yang belum ada di products_rows.csv.
 * Aman dijalankan ulang: pakai upsert berdasarkan SKU, jadi tidak bikin duplikat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasTipe = Schema::hasColumn('products', 'tipe');
        $now = now();

        // [sku, nama_produk, kategori, harga, stok_minimum, tipe]
        $data = [
            ['AUTO-0037', 'Nubiko Baby Wash 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0038', 'NUBIKO Hydra Gentle Body Wash with Ectoin & Actibiome™ 200 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0039', 'Nubiko HydraSavior Ointment Balm with 2% Derma-Clera™ 15 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0040', 'Nubiko Smooth and Shine 2in1 Shampoo & Conditioner Anak 200 ml', null, 0.0, 10, 'satuan'],
        ];

        $rows = [];
        foreach ($data as [$sku, $nama, $kategori, $harga, $stokMin, $tipe]) {
            $row = [
                'sku' => $sku,
                'nama_produk' => $nama,
                'kategori' => $kategori,
                'harga' => $harga,
                'stok_minimum' => $stokMin,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasTipe) {
                $row['tipe'] = $tipe;
            }
            $rows[] = $row;
        }

        DB::table('products')->upsert($rows, ['sku'], ['nama_produk', 'updated_at']);
    }

    public function down(): void
    {
        // Sengaja kosong: jangan hapus data produk saat rollback.
    }
};
