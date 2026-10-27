<?php

namespace App\Http\Controllers;

use App\Models\Restock;
use App\Exports\RestockExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class RestockController extends Controller
{
    private function buildQuery(Request $request)
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

        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->buildQuery($request);

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
                'Warehouse' => $item->kota_asal_gudang,
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
            'nama_produk' => 'required|string|max:255|exists:products,nama_produk',
            'qty' => 'required|integer|min:1',
            'status' => 'required|in:sudah,belum',
        ]);

        Restock::create($validated);

        return response()->json(['message' => 'Data berhasil ditambahkan']);
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

        $lainnyaDetail = [];
        if ($rest->count() > 0) {
            $byKotaFinal->push(['kota' => 'Lainnya', 'total_qty' => (int) $rest->sum('total_qty')]);
            $lainnyaDetail = $rest->map(fn($r) => ['kota' => $r->kota_asal_gudang, 'total_qty' => (int) $r->total_qty])->values();
        }

        return response()->json([
            'monthly' => $trend,
            'by_kota' => $byKotaFinal->values(),
            'lainnya_detail' => $lainnyaDetail,
        ]);
    }

    public function productNames()
    {
        $names = \App\Models\Product::orderBy('nama_produk')->pluck('nama_produk');
        return response()->json(['data' => $names]);
    }

    public function listProduk()
    {
        $produk = Restock::where('status', 'sudah')
            ->select('nama_produk')
            ->distinct()
            ->orderBy('nama_produk')
            ->pluck('nama_produk');

        return response()->json(['data' => $produk]);
    }

    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,xls|max:5120',
        ]);

        $rows = \Maatwebsite\Excel\Facades\Excel::toArray([], $request->file('file'))[0];
        $headers = array_map(fn($h) => strtolower(trim(str_replace(' ', '_', (string) $h))), array_shift($rows));

        $preview = [];
        foreach ($rows as $row) {
            if (count(array_filter($row, fn($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $assoc = array_combine($headers, array_pad($row, count($headers), null));

            $tanggalRaw = $assoc['tanggal_kirim'] ?? null;
            $kota = trim((string) ($assoc['kota_asal_gudang'] ?? ''));
            $nama = trim((string) ($assoc['nama_produk'] ?? ''));
            $qty = $assoc['qty'] ?? null;
            $statusRaw = trim((string) ($assoc['status'] ?? ''));

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

            $statusNormalized = null;
            $statusLower = strtolower($statusRaw);
            if ($statusLower === 'sudah' || $statusLower === 'sudah inbound') {
                $statusNormalized = 'sudah';
            } elseif ($statusLower === 'belum' || $statusLower === 'belum inbound') {
                $statusNormalized = 'belum';
            }

            $errors = [];
            if (empty($tanggalFormatted)) $errors[] = 'Tanggal Kirim kosong/tidak valid';
            if (empty($kota)) $errors[] = 'Warehouse kosong';
            if (empty($nama)) $errors[] = 'Nama Produk kosong';
            if ($qty === null || $qty === '' || !is_numeric($qty)) $errors[] = 'Qty kosong/tidak valid';
            if (empty($statusNormalized)) $errors[] = 'Status kosong/tidak dikenali';

            // Tandai produk yang belum ada di master (bukan error, tapi akan auto-didaftarkan)
            $produkBaru = !empty($nama) && !\App\Models\Product::where('nama_produk', $nama)->exists();

            $preview[] = [
                'tanggal_kirim' => $tanggalFormatted,
                'kota_asal_gudang' => $kota,
                'nama_produk' => $nama,
                'qty' => is_numeric($qty) ? (int) $qty : null,
                'status' => $statusNormalized,
                'errors' => $errors,
                'valid' => count($errors) === 0,
                'produk_baru' => $produkBaru,
            ];
        }

        return response()->json(['data' => $preview]);
    }

    public function importConfirm(Request $request)
    {
        $validated = $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.tanggal_kirim' => 'required|date',
            'rows.*.kota_asal_gudang' => 'required|string',
            'rows.*.nama_produk' => 'required|string',
            'rows.*.qty' => 'required|integer|min:1',
            'rows.*.status' => 'required|in:sudah,belum',
        ]);

        // Auto-daftarkan produk yang belum ada di master produk
        $existingNames = \App\Models\Product::pluck('nama_produk')->toArray();
        $allNamaFromRows = collect($validated['rows'])->pluck('nama_produk')->unique()->values();
        $newNames = $allNamaFromRows->diff($existingNames)->values();

        if ($newNames->count() > 0) {
            $lastNumber = \App\Models\Product::where('sku', 'like', 'AUTO-%')
                ->get()
                ->map(fn($p) => (int) str_replace('AUTO-', '', $p->sku))
                ->max() ?? 0;

            $now = now();
            $newRows = [];
            foreach ($newNames as $nama) {
                $lastNumber++;
                $newRows[] = [
                    'sku'          => 'AUTO-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT),
                    'nama_produk'  => $nama,
                    'kategori'     => null,
                    'harga'        => 0,
                    'stok_minimum' => 10,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            foreach (array_chunk($newRows, 100) as $chunk) {
                \App\Models\Product::insert($chunk);
            }
        }

        foreach ($validated['rows'] as $row) {
            Restock::create($row);
        }

        return response()->json([
            'message'       => 'Import berhasil',
            'count'         => count($validated['rows']),
            'produk_baru'   => $newNames->count(),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $query = $this->buildQuery($request)->orderBy('tanggal_kirim', 'asc')->orderBy('id', 'asc');
        $items = $query->get();
        $filename = 'laporan-restock-' . now()->format('Ymd_His') . '.csv';

        return Excel::download(new RestockExport($items, $request->all()), $filename, \Maatwebsite\Excel\Excel::CSV, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = $this->buildQuery($request)->orderBy('tanggal_kirim', 'asc')->orderBy('id', 'asc');
        $items = $query->get();
        $filename = 'laporan-restock-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new RestockExport($items, $request->all()), $filename);
    }

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $query = $this->buildQuery($request)->orderBy('tanggal_kirim', 'asc')->orderBy('id', 'asc');
        $items = $query->get();
        $totalQty = $items->sum('qty');
        $totalSudah = $items->where('status', 'sudah')->count();
        $totalBelum = $items->where('status', 'belum')->count();

        $pdf = Pdf::loadView('exports.restock-pdf', [
            'items' => $items,
            'totalQty' => $totalQty,
            'totalSudah' => $totalSudah,
            'totalBelum' => $totalBelum,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
            'printedAt' => now()->translatedFormat('d F Y, H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'laporan-restock-' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($filename);
    }
}