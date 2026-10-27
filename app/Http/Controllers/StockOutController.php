<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Restock;
use App\Models\StockOut;
use App\Exports\StockOutExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class StockOutController extends Controller
{
    private function buildQuery(Request $request)
    {
        $query = StockOut::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_produk', 'ilike', "%{$search}%")
                  ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('tanggal_keluar', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('tanggal_keluar', '<=', $request->date_to);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->buildQuery($request);

        $perPage = 25;
        $paginated = $query->orderByDesc('tanggal_keluar')->paginate($perPage);

        $data = collect($paginated->items())->values()->map(function ($item, $index) use ($paginated) {
            $globalNo = ($paginated->currentPage() - 1) * $paginated->perPage() + $index + 1;
            return [
                'No' => $globalNo,
                'Tanggal Keluar' => $item->tanggal_keluar->format('Y-m-d'),
                'SKU' => $item->sku,
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
            'sku' => 'required|string|exists:products,sku',
            'qty' => 'required|integer|min:1',
            'keterangan' => 'nullable|string|max:255',
        ], [
            'sku.exists' => 'SKU tidak ditemukan di Master Produk.',
        ]);

        $product = Product::where('sku', $validated['sku'])->firstOrFail();

        if (!Restock::where('nama_produk', $product->nama_produk)->exists()) {
            return response()->json([
                'message' => 'Produk tersebut belum pernah di-restock.',
                'errors' => ['sku' => ['Produk tersebut belum pernah di-restock, pilih produk lain.']],
            ], 422);
        }

        StockOut::create([
            'tanggal_keluar' => $validated['tanggal_keluar'],
            'sku' => $product->sku,
            'nama_produk' => $product->nama_produk,
            'qty' => $validated['qty'],
            'keterangan' => $validated['keterangan'] ?? null,
        ]);

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

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
        ]);

        $rows = \Maatwebsite\Excel\Facades\Excel::toArray([], $request->file('file'))[0];
        $headers = array_map(fn($h) => strtolower(trim(str_replace(' ', '_', (string) $h))), array_shift($rows));

        $products = Product::all();
        $bySku = $products->keyBy('sku');
        $byName = $products->keyBy('nama_produk');
        $restockedNames = Restock::select('nama_produk')->distinct()->pluck('nama_produk')->flip();

        $preview = [];
        foreach ($rows as $row) {
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $cells = array_slice(array_pad($row, count($headers), null), 0, count($headers));
            $assoc = array_combine($headers, $cells);

            $tanggalRaw = $assoc['tanggal_keluar'] ?? null;
            $skuInput = trim((string) ($assoc['sku'] ?? ''));
            $namaInput = trim((string) ($assoc['nama_produk'] ?? ''));
            $qty = $assoc['qty'] ?? null;
            $keterangan = trim((string) ($assoc['keterangan'] ?? ''));

            $tanggalFormatted = null;
            if (is_numeric($tanggalRaw)) {
                try {
                    $tanggalFormatted = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggalRaw)->format('Y-m-d');
                } catch (\Exception $e) {}
            } elseif (!empty($tanggalRaw)) {
                try {
                    $tanggalFormatted = Carbon::parse($tanggalRaw)->format('Y-m-d');
                } catch (\Exception $e) {}
            }

            $errors = [];
            $product = null;

            if ($skuInput !== '') {
                $product = $bySku->get($skuInput);
                if (!$product) $errors[] = 'SKU tidak ditemukan di Master Produk';
            } elseif ($namaInput !== '') {
                $product = $byName->get($namaInput);
                if (!$product) $errors[] = 'Nama Produk belum terdaftar di Master Produk';
            } else {
                $errors[] = 'SKU / Nama Produk kosong';
            }

            if ($product && !$restockedNames->has($product->nama_produk)) {
                $errors[] = 'Produk belum pernah di-restock';
            }

            if (empty($tanggalFormatted)) $errors[] = 'Tanggal Keluar kosong/tidak valid';
            if (!is_numeric($qty) || (int) $qty < 1) $errors[] = 'Qty harus angka minimal 1';
            if (mb_strlen($keterangan) > 255) $errors[] = 'Keterangan terlalu panjang (maks 255 karakter)';

            $preview[] = [
                'tanggal_keluar' => $tanggalFormatted,
                'sku' => $product ? $product->sku : ($skuInput !== '' ? $skuInput : null),
                'nama_produk' => $product ? $product->nama_produk : $namaInput,
                'qty' => is_numeric($qty) ? (int) $qty : null,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'errors' => $errors,
                'valid' => count($errors) === 0,
            ];
        }

        return response()->json(['data' => $preview]);
    }

    public function importConfirm(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.tanggal_keluar' => 'required|date',
            'rows.*.sku' => 'required|string|exists:products,sku',
            'rows.*.qty' => 'required|integer|min:1',
            'rows.*.keterangan' => 'nullable|string|max:255',
        ]);

        $skus = collect($validated['rows'])->pluck('sku')->unique();
        $products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

        $restocked = Restock::whereIn('nama_produk', $products->pluck('nama_produk'))
            ->distinct()
            ->pluck('nama_produk')
            ->flip();

        foreach ($products as $product) {
            if (!$restocked->has($product->nama_produk)) {
                return response()->json([
                    'message' => "Produk {$product->nama_produk} belum pernah di-restock.",
                ], 422);
            }
        }

        $now = now();
        $records = collect($validated['rows'])->map(fn($row) => [
            'tanggal_keluar' => $row['tanggal_keluar'],
            'sku' => $row['sku'],
            'nama_produk' => $products[$row['sku']]->nama_produk,
            'qty' => $row['qty'],
            'keterangan' => $row['keterangan'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        StockOut::insert($records);

        return response()->json(['message' => 'Import berhasil', 'count' => count($records)]);
    }

    public function exportCsv(Request $request)
    {
        $query = $this->buildQuery($request)->orderBy('tanggal_keluar', 'asc')->orderBy('id', 'asc');
        $items = $query->get();
        $filename = 'laporan-stok-keluar-' . now()->format('Ymd_His') . '.csv';

        return Excel::download(new StockOutExport($items, $request->all()), $filename, \Maatwebsite\Excel\Excel::CSV, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = $this->buildQuery($request)->orderBy('tanggal_keluar', 'asc')->orderBy('id', 'asc');
        $items = $query->get();
        $filename = 'laporan-stok-keluar-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new StockOutExport($items, $request->all()), $filename);
    }

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $query = $this->buildQuery($request)->orderBy('tanggal_keluar', 'asc')->orderBy('id', 'asc');
        $items = $query->get();
        $totalQty = $items->sum('qty');

        $pdf = Pdf::loadView('exports.stock-out-pdf', [
            'items' => $items,
            'totalQty' => $totalQty,
            'filters' => [
                'search' => $request->search,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
            'printedAt' => now()->translatedFormat('d F Y, H:i'),
        ])->setPaper('a4', 'portrait');

        $filename = 'laporan-stok-keluar-' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }
}