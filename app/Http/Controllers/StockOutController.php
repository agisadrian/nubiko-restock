<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StockOutController extends Controller
{
    public function index(Request $request)
    {
        $query = StockOut::query();

        if ($request->filled('search')) {
            $query->where('nama_produk', 'ilike', '%' . $request->search . '%');
        }

        $perPage = 25;
        $paginated = $query->orderByDesc('tanggal_keluar')->paginate($perPage);

        $data = collect($paginated->items())->values()->map(function ($item, $index) use ($paginated) {
            $globalNo = ($paginated->currentPage() - 1) * $paginated->perPage() + $index + 1;
            return [
                'No' => $globalNo,
                'Tanggal Keluar' => $item->tanggal_keluar->format('Y-m-d'),
                'Nama Produk' => $item->nama_produk,
                'QTY' => $item->qty,
                'Keterangan' => $item->keterangan,
                'id' => $item->id,
            ];
        });

        $totalQty = (clone $query)->sum('qty');

        return response()->json([
            'data' => $data,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'total_qty' => (int) $totalQty,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal_keluar' => 'required|date',
            'nama_produk' => 'required|string|max:255|exists:restocks,nama_produk',
            'qty' => 'required|integer|min:1',
            'keterangan' => 'nullable|string|max:255',
        ], [
            'nama_produk.exists' => 'Produk tersebut belum pernah di-restock, pilih dari daftar produk yang ada.',
        ]);

        StockOut::create($validated);

        return response()->json(['message' => 'Data stok keluar berhasil ditambahkan']);
    }

    public function destroy(int $id)
    {
        StockOut::findOrFail($id)->delete();

        return response()->json(['message' => 'Data berhasil dihapus']);
    }

    public function chartStats(Request $request)
    {
        $query = StockOut::query();

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_keluar', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_keluar', '<=', $request->date_to);
        }

        $granularity = $request->get('granularity', 'month');
        $format = $granularity === 'day' ? 'YYYY-MM-DD' : 'YYYY-MM';
        $displayFormat = $granularity === 'day' ? 'd M' : 'M Y';

        $trend = $query
            ->selectRaw("to_char(tanggal_keluar, '{$format}') as periode, sum(qty) as total_qty")
            ->groupBy('periode')
            ->orderBy('periode')
            ->get()
            ->map(function ($row) use ($granularity, $displayFormat) {
                $date = $granularity === 'day' ? $row->periode : $row->periode . '-01';
                return [
                    'periode' => Carbon::parse($date)->translatedFormat($displayFormat),
                    'total_qty' => (int) $row->total_qty,
                ];
            });

        return response()->json(['data' => $trend]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
        ]);

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\StockOutsImport, $request->file('file'));

        return response()->json(['message' => 'Import berhasil']);
    }
}