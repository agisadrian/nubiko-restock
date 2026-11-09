<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membangun ulang Master Product (30 produk, tanpa duplikat).
 * Aman dijalankan ulang: upsert berdasarkan SKU.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasTipe = Schema::hasColumn('products', 'tipe');
        $now = now();

        // [sku, nama_produk]
        $data = [
            ['AUTO-0001', 'NUBIKO Baby Hand & Body Lotion with Avocado + Aloe 100 gr'],
            ['AUTO-0002', 'Nubiko Free Gift (FG01)'],
            ['AUTO-0005', 'Master Box isi 12 pcs - Nubiko Hair Lotion 7x Botanical Oil Complex 100 ml'],
            ['AUTO-0006', 'Master Box isi 12 pcs - Nubiko Baby Cream 50 gr'],
            ['AUTO-0007', 'Nubiko Essential Soothe with Melicica 15 gr'],
            ['AUTO-0008', 'Nubiko Hair Lotion 7x Botanical Oil Complex 100 ml'],
            ['AUTO-0009', 'Nubiko Hydra Gentle Facial Foam with Ectoin & Biome Balancer 50 ml'],
            ['AUTO-0011', 'Nubiko Skin Pair Cream with 1% Bisabolol 50gr'],
            ['AUTO-0012', 'Master Box isi 12 pcs - Nubiko Physical Sunscreen SPF 45 PA ++++ 30ml'],
            ['AUTO-0014', 'NUBIKO Baby Cream 50 gr'],
            ['AUTO-0015', 'Nubiko Essential Soothe with Melicica 2 gr'],
            ['AUTO-0017', 'Nubiko Be Calm Days Eau de Cologne 100 ml'],
            ['AUTO-0018', 'Nubiko Ready, Set, Explore! Eau de Cologne 100 ml'],
            ['AUTO-0020', 'Nubiko Physical Sunscreen 2 gr'],
            ['AUTO-0021', 'Nubiko Game On Cologne 100 ml'],
            ['AUTO-0023', 'Master Box isi 12 pcs - Nubiko Essential Soothe with Melicica 15 gr'],
            ['AUTO-0024', 'Nubiko Hydra Gentle Body Lotion with Avocado Oil+Shea Butter & Plussome Technology 100 gr'],
            ['AUTO-0026', 'Produk Free Gift Nubiko 2 (FG02)'],
            ['AUTO-0027', 'Nubiko Skin Pair Cream with 1% Bisabolol 2gr'],
            ['AUTO-0028', 'Nubiko Physical Sunscreen SPF 45 PA ++++ 30ml'],
            ['AUTO-0029', 'Nubiko Essential Soothe with Melicica 30 gr'],
            ['AUTO-0030', 'Nubiko Free Gift Sunglasess'],
            ['AUTO-0031', 'Nubiko Hydra Gentle Body Lotion 2 gr'],
            ['AUTO-0033', 'Master Box isi 12 pcs - Nubiko Skin Pair Cream with 1% Bisabolol 50gr'],
            ['AUTO-0034', 'Master Box isi 12 pcs - Nubiko Essential Soothe with Melicica 30 gr'],
            ['AUTO-0036', 'Produk Free Gift Nubiko 3 (FG03)'],
            ['AUTO-0037', 'Nubiko Baby Wash 100 ml'],
            ['AUTO-0038', 'NUBIKO Hydra Gentle Body Wash with Ectoin & Actibiome™ 200 ml'],
            ['AUTO-0039', 'Nubiko HydraSavior Ointment Balm with 2% Derma-Clera™ 15 gr'],
            ['AUTO-0040', 'Nubiko Smooth and Shine 2in1 Shampoo & Conditioner Anak 200 ml'],
        ];

        $rows = [];
        foreach ($data as [$sku, $nama]) {
            $row = [
                'sku' => $sku,
                'nama_produk' => $nama,
                'kategori' => null,
                'harga' => 0,
                'stok_minimum' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasTipe) {
                $row['tipe'] = 'satuan';
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