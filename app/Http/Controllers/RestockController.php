<?php

namespace App\Http\Controllers;

use App\Models\Restock;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RestockController extends Controller
{
    public function index(Request $request)
{
    $query = Restock::query();

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('nama_produk', 'ilike', "%{$search}%")
              ->orWhere('kota_asal_gudang', 'ilike', "%{$search}%");
        });
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('date_from')) {
    $query->whereDate('tanggal_kirim', '>=', $request->date_from);
    }

    if ($request->filled('date_to')) {
        $query->whereDate('tanggal_kirim', '<=', $request->date_to);
    }

    $statsResult = (clone $query)->selectRaw("
    count(*) as total,
    coalesce(sum(qty), 0) as total_qty,
    count(*) filter (where status = 'sudah') as sudah,
    count(*) filter (where status = 'belum') as belum
")->first();

$stats = [
    'total' => (int) $statsResult->total,
    'total_qty' => (int) $statsResult->total_qty,
    'sudah' => (int) $statsResult->sudah,
    'belum' => (int) $statsResult->belum,
];

    $perPage = 25;
    $paginated = $query->orderBy('tanggal_kirim')->paginate($perPage);

    $data = collect($paginated->items())->values()->map(function ($item, $index) use ($paginated) {
        $globalNo = ($paginated->currentPage() - 1) * $paginated->perPage() + $index + 1;
        return [
            'No' => $globalNo,
            'Bulan' => Carbon::parse($item->tanggal_kirim)->translatedFormat('F'),
            'Tanggal Kirim' => $item->tanggal_kirim->format('Y-m-d'),
            'Kota Asal Gudang' => $item->kota_asal_gudang,
            'Nama Produk' => $item->nama_produk,
            'QTY' => $item->qty,
            'Status' => $item->status === 'sudah' ? 'Sudah Inbound' : 'Belum Inbound',
            'row_number' => $item->id,
        ];
    });

    return response()->json([
        'data' => $data,
        'current_page' => $paginated->currentPage(),
        'last_page' => $paginated->lastPage(),
        'total' => $paginated->total(),
        'stats' => $stats,
    ]);
}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal_kirim' => 'required|date',
            'kota_asal_gudang' => 'required|string|max:255',
            'nama_produk' => 'required|string|max:255',
            'qty' => 'required|integer|min:1',
            'status' => 'required|in:sudah,belum',
        ]);

        Restock::create($validated);

        return response()->json(['message' => 'Data berhasil ditambahkan']);
    }

    /**
     * Daftar nama produk unik yang sudah pernah di-restock.
     * Dipakai untuk dropdown "pilih produk" di form Stok Keluar,
     * supaya stok keluar cuma bisa mencatat produk yang memang ada di restock.
     */
    public function productNames()
    {
        $produk = Restock::query()
            ->select('nama_produk')
            ->distinct()
            ->orderBy('nama_produk')
            ->pluck('nama_produk');

        return response()->json(['data' => $produk]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:sudah,belum',
        ]);

        $restock = Restock::findOrFail($id);
        $restock->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Status berhasil diupdate']);
    }

    public function destroy(int $id)
    {
        Restock::findOrFail($id)->delete();

        return response()->json(['message' => 'Data berhasil dihapus']);
    }

    public function import(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,csv,xls|max:5120',
    ]);

    \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\RestocksImport, $request->file('file'));

    return response()->json(['message' => 'Import berhasil']);
}

public function chartStats(Request $request)
{
    $query = Restock::query();

    if ($request->filled('date_from')) {
        $query->whereDate('tanggal_kirim', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('tanggal_kirim', '<=', $request->date_to);
    }

    $granularity = $request->get('granularity', 'month');
    $format = $granularity === 'day' ? 'YYYY-MM-DD' : 'YYYY-MM';
    $displayFormat = $granularity === 'day' ? 'd M' : 'M Y';

    $trend = (clone $query)
        ->selectRaw("to_char(tanggal_kirim, '{$format}') as periode, sum(qty) as total_qty, count(*) as total_kiriman")
        ->groupBy('periode')
        ->orderBy('periode')
        ->get()
        ->map(function ($row) use ($granularity, $displayFormat) {
            $date = $granularity === 'day' ? $row->periode : $row->periode . '-01';
            return [
                'periode' => Carbon::parse($date)->translatedFormat($displayFormat),
                'total_qty' => (int) $row->total_qty,
                'total_kiriman' => (int) $row->total_kiriman,
            ];
        });

    $byKota = (clone $query)
        ->selectRaw('kota_asal_gudang, sum(qty) as total_qty')
        ->groupBy('kota_asal_gudang')
        ->orderByDesc('total_qty')
        ->get();

    $top = $byKota->take(7);
    $rest = $byKota->skip(7);
    $byKotaFinal = $top->map(fn($r) => ['kota' => $r->kota_asal_gudang, 'total_qty' => (int) $r->total_qty]);
    if ($rest->count() > 0) {
        $byKotaFinal->push(['kota' => 'Lainnya', 'total_qty' => (int) $rest->sum('total_qty')]);
    }

    return response()->json([
        'monthly' => $trend,
        'by_kota' => $byKotaFinal->values(),
    ]);
}
}