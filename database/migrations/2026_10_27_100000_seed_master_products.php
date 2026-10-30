<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Isi awal Master Product (36 produk dari products_rows.csv).
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
            ['AUTO-0001', 'NUBIKO Baby Hand & Body Lotion with Avocado + Aloe 100 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0002', 'Nubiko Free Gift (FG01)', null, 0.0, 10, 'satuan'],
            ['AUTO-0003', 'NUBIKO Hair Lotion with Argan Oil Candlenut Oil 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0004', 'NUBIKO Essential Soothe 30 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0005', 'Master Box isi 12 pcs - Nubiko Hair Lotion 100 ml with 7x Botanical Oil Complex 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0006', 'Master Box isi 12 pcs - Nubiko Baby Cream 50 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0007', 'Nubiko Essential Soothe with Melicica 15 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0008', 'Nubiko Hair Lotion 7x Botanical Oil Complex 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0009', 'Nubiko Hydra Gentle Facial Foam with Ectoin & Biome Balancer 50 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0010', 'NUBIKO Essential Soothe 14,5 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0011', 'Nubiko Skin Pair Cream with 1% Bisabolol 50gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0012', 'Master Box isi 12 pcs - Nubiko Physical Sunscreen SPF 45 PA ++++ 30 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0013', 'Masterbox Nubiko Baby Cream 50 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0014', 'NUBIKO Baby Cream 50 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0015', 'Nubiko Essential Soothe with Melicica 2 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0016', 'Masterbox Nubiko Essential Soothe 30 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0017', 'Nubiko Be Calm Days Eau de Cologne 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0018', 'Nubiko Ready, Set, Explore! Eau de Cologne 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0019', 'Materbox Nubiko Hair Lotion with Argan Oil Candlenut Oil 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0020', 'Nubiko Physical Sunscreen 2 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0021', 'Nubiko Game On Cologne 100 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0022', 'Produk Free Gift Nubiko 1 (FG01)', null, 0.0, 10, 'satuan'],
            ['AUTO-0023', 'Master Box isi 12 pcs - Nubiko Essential Soothe with melicica 15 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0024', 'Nubiko Hydra Gentle Body Lotion with Avocado Oil+Shea Butter & Plussome Technology 100 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0025', 'Nubiko Baby Wash 280 ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0026', 'Produk Free Gift Nubiko 2  (FG02)', null, 0.0, 10, 'satuan'],
            ['AUTO-0027', 'Nubiko Skin Pair Cream with 1% Bisabolol 2gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0028', 'Nubiko Physical Sunscreen SPF 45 PA ++++ 30ml', null, 0.0, 10, 'satuan'],
            ['AUTO-0029', 'Nubiko Essential Soothe with Melicica 30 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0030', 'Nubiko Free Gift Sunglasess', null, 0.0, 10, 'satuan'],
            ['AUTO-0031', 'Nubiko Hydra Gentle Body Lotion 2 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0032', 'Produk Free Gift Nubiko 1', null, 0.0, 10, 'satuan'],
            ['AUTO-0033', 'Master Box isi 12 pcs - Nubiko Skin Pair 50 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0034', 'Master Box isi 12 pcs Nubiko Essential Soothe with melicica 30 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0035', 'Masterbox Nubiko Essential Soothe 14,5 gr', null, 0.0, 10, 'satuan'],
            ['AUTO-0036', 'Produk Free Gift Nubiko 3  (FG03)', null, 0.0, 10, 'satuan'],
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

        // Kolom yang di-update kalau SKU sudah ada: nama saja, harga/kategori/stok yang sudah diedit tidak ditimpa.
        DB::table('products')->upsert($rows, ['sku'], ['nama_produk', 'updated_at']);
    }

    public function down(): void
    {
        // Sengaja kosong: jangan hapus data produk saat rollback.
    }
};