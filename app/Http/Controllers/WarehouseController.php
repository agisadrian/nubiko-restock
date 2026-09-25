<?php

namespace App\Http\Controllers;

use App\Models\Restock;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        // Ringkasan per warehouse
        $summary = Restock::selectRaw('kota_asal_gudang, sum(qty) as total_qty, count(*) as total_kiriman, count(distinct nama_produk) as total_produk')
            ->groupBy('kota_asal_gudang')
            ->orderByDesc('total_qty')
            ->get();

        // Breakdown produk per warehouse
        $produkPerWarehouse = Restock::selectRaw('kota_asal_gudang, nama_produk, sum(qty) as total_qty, count(*) as jumlah_kiriman')
            ->groupBy('kota_asal_gudang', 'nama_produk')
            ->orderByDesc('total_qty')
            ->get()
            ->groupBy('kota_asal_gudang');

        $data = $summary->map(function ($w) use ($produkPerWarehouse) {
            $produk = ($produkPerWarehouse[$w->kota_asal_gudang] ?? collect())->map(function ($p) {
                return [
                    'nama_produk' => $p->nama_produk,
                    'total_qty' => (int) $p->total_qty,
                    'jumlah_kiriman' => (int) $p->jumlah_kiriman,
                ];
            })->values();

            return [
                'warehouse' => $w->kota_asal_gudang,
                'total_qty' => (int) $w->total_qty,
                'total_kiriman' => (int) $w->total_kiriman,
                'total_produk' => (int) $w->total_produk,
                'produk' => $produk,
            ];
        });

        return response()->json(['data' => $data]);
    }
}