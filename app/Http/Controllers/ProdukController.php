<?php

namespace App\Http\Controllers;

use App\Models\Restock;
use App\Models\StockOut;
use App\Models\Product;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        $masuk = Restock::where('status', 'sudah')
            ->selectRaw('nama_produk, sum(qty) as total_masuk')
            ->groupBy('nama_produk')
            ->pluck('total_masuk', 'nama_produk');

        $keluar = StockOut::selectRaw('nama_produk, sum(qty) as total_keluar')
            ->groupBy('nama_produk')
            ->pluck('total_keluar', 'nama_produk');

        $stokMinimumMap = Product::pluck('stok_minimum', 'nama_produk');

        $semuaProduk = $masuk->keys()->merge($keluar->keys())->unique()->sort()->values();

        $data = $semuaProduk->map(function ($nama) use ($masuk, $keluar, $stokMinimumMap) {
            $totalMasuk = (int) ($masuk[$nama] ?? 0);
            $totalKeluar = (int) ($keluar[$nama] ?? 0);
            return [
                'nama_produk' => $nama,
                'total_masuk' => $totalMasuk,
                'total_keluar' => $totalKeluar,
                'sisa_stok' => $totalMasuk - $totalKeluar,
                'stok_minimum' => (int) ($stokMinimumMap[$nama] ?? 10),
            ];
        });

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $data = $data->filter(fn($item) => str_contains(strtolower($item['nama_produk']), $search))->values();
        }

        return response()->json(['data' => $data]);
    }
}