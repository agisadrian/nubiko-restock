<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductFromExcelSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Master Box isi 12 pcs - Nubiko Baby Cream 50 gr',
            'Master Box isi 12 pcs - Nubiko Essential Soothe with melicica 15 gr',
            'Master Box isi 12 pcs - Nubiko Hair Lotion 100 ml with 7x Botanical Oil Complex 100 ml',
            'Master Box isi 12 pcs - Nubiko Physical Sunscreen SPF 45 PA ++++ 30 ml',
            'Master Box isi 12 pcs - Nubiko Skin Pair 50 gr',
            'Master Box isi 12 pcs Nubiko Essential Soothe with melicica 30 gr',
            'Masterbox Nubiko Baby Cream 50 gr',
            'Masterbox Nubiko Essential Soothe 14,5 gr',
            'Masterbox Nubiko Essential Soothe 30 gr',
            'Materbox Nubiko Hair Lotion with Argan Oil Candlenut Oil 100 ml',
            'NUBIKO Baby Cream 50 gr',
            'NUBIKO Baby Hand & Body Lotion with Avocado + Aloe 100 gr',
            'NUBIKO Essential Soothe 14,5 gr',
            'NUBIKO Essential Soothe 30 gr',
            'NUBIKO Hair Lotion with Argan Oil Candlenut Oil 100 ml',
            'Nubiko Baby Wash 280 ml',
            'Nubiko Be Calm Days Eau de Cologne 100 ml',
            'Nubiko Essential Soothe with Melicica 15 gr',
            'Nubiko Essential Soothe with Melicica 2 gr',
            'Nubiko Essential Soothe with Melicica 30 gr',
            'Nubiko Free Gift (FG01)',
            'Nubiko Free Gift Sunglasess',
            'Nubiko Game On Cologne 100 ml',
            'Nubiko Hair Lotion 7x Botanical Oil Complex 100 ml',
            'Nubiko Hydra Gentle Body Lotion 2 gr',
            'Nubiko Hydra Gentle Body Lotion with Avocado Oil+Shea Butter & Plussome Technology 100 gr',
            'Nubiko Hydra Gentle Facial Foam with Ectoin & Biome Balancer 50 ml',
            'Nubiko Physical Sunscreen 2 gr',
            'Nubiko Physical Sunscreen SPF 45 PA ++++ 30ml',
            'Nubiko Ready, Set, Explore! Eau de Cologne 100 ml',
            'Nubiko Skin Pair Cream with 1% Bisabolol 2gr',
            'Nubiko Skin Pair Cream with 1% Bisabolol 50gr',
            'Produk Free Gift Nubiko 1',
            'Produk Free Gift Nubiko 1 (FG01)',
            'Produk Free Gift Nubiko 2  (FG02)',
            'Produk Free Gift Nubiko 3  (FG03)',
        ];

        $last = Product::where('sku', 'like', 'AUTO-%')
            ->pluck('sku')
            ->map(fn ($s) => (int) str_replace('AUTO-', '', $s))
            ->max() ?? 0;

        foreach ($names as $nama) {
            if (Product::where('nama_produk', $nama)->exists()) {
                continue;
            }
            $last++;
            Product::create([
                'sku' => 'AUTO-' . str_pad($last, 4, '0', STR_PAD_LEFT),
                'nama_produk' => $nama,
                'kategori' => null,
                'harga' => 0,
                'stok_minimum' => 10,
            ]);
        }
    }
}
