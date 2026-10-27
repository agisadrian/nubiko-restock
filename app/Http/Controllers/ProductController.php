<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Restock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_produk', 'ilike', "%{$search}%")
                  ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        $products = $query->orderBy('nama_produk')->get();

        return response()->json(['data' => $products]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:100|unique:products,sku',
            'tipe' => 'sometimes|in:satuan,bundling',
            'nama_produk' => 'required|string|max:255',
            'kategori' => 'nullable|string|max:100',
            'harga' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|integer|min:0',
        ]);

        $product = Product::create($validated);

        return response()->json(['message' => 'Produk berhasil ditambahkan', 'data' => $product]);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'sku' => 'required|string|max:100|unique:products,sku,' . $id,
            'tipe' => 'sometimes|in:satuan,bundling',
            'nama_produk' => 'required|string|max:255',
            'kategori' => 'nullable|string|max:100',
            'harga' => 'nullable|numeric|min:0',
            'stok_minimum' => 'nullable|integer|min:0',
        ]);

        $product->update($validated);

        return response()->json(['message' => 'Produk berhasil diupdate']);
    }

    public function destroy(int $id)
    {
        Product::findOrFail($id)->delete();

        return response()->json(['message' => 'Produk berhasil dihapus']);
    }

    public function generateFromRestock()
    {
        $existingNames = Product::pluck('nama_produk')->toArray();

        $namaProdukRestock = Restock::select('nama_produk')
            ->distinct()
            ->pluck('nama_produk');

        $toCreate = $namaProdukRestock->diff($existingNames)->values();

        $lastNumber = Product::where('sku', 'like', 'AUTO-%')
            ->get()
            ->map(fn($p) => (int) str_replace('AUTO-', '', $p->sku))
            ->max() ?? 0;

        $rows = [];
        $now = now();
        foreach ($toCreate as $nama) {
            $lastNumber++;
            $rows[] = [
                'sku' => 'AUTO-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT),
                'nama_produk' => $nama,
                'kategori' => null,
                'harga' => 0,
                'stok_minimum' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($rows) > 0) {
            foreach (array_chunk($rows, 100) as $chunk) {
                Product::insert($chunk);
            }
        }

        return response()->json(['message' => 'Berhasil generate ' . count($rows) . ' produk baru', 'count' => count($rows)]);
    }

    public function listNames()
    {
        $names = Product::orderBy('nama_produk')->pluck('nama_produk');

        return response()->json(['data' => $names]);
    }

    public function options()
    {
        $products = Product::orderBy('nama_produk')->get(['sku', 'nama_produk', 'tipe']);

        return response()->json(['data' => $products]);
    }

    public function import(Request $request)
{
    $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

    $handle = fopen($request->file('file')->getRealPath(), 'r');
    $first = preg_replace('/^\xEF\xBB\xBF/', '', (string) fgets($handle));
    $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    $header = array_map(fn($h) => strtolower(trim($h)), str_getcsv($first, $delimiter));

    if (!in_array('sku', $header) || !in_array('nama_produk', $header)) {
        fclose($handle);
        return response()->json(['message' => 'Header CSV harus punya kolom sku dan nama_produk'], 422);
    }

    $hasTipe = Schema::hasColumn('products', 'tipe');
    $now = now();
    $rows = [];

    while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
        $line = array_pad($line, count($header), null);
        $data = array_combine($header, array_slice($line, 0, count($header)));

        $sku = trim($data['sku'] ?? '');
        $nama = trim($data['nama_produk'] ?? '');
        if ($sku === '' || $nama === '') {
            continue;
        }

        $row = [
            'sku' => $sku,
            'nama_produk' => $nama,
            'kategori' => trim($data['kategori'] ?? '') ?: null,
            'harga' => (float) preg_replace('/[^0-9.]/', '', $data['harga'] ?? '0'),
            'stok_minimum' => (int) (($data['stok_minimum'] ?? '') !== '' ? $data['stok_minimum'] : 10),
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if ($hasTipe) {
            $tipe = strtolower(trim($data['tipe'] ?? ''));
            $row['tipe'] = in_array($tipe, ['satuan', 'bundling']) ? $tipe : 'satuan';
        }

        $rows[$sku] = $row; // keyed by sku biar baris ganda gak bikin error
    }
    fclose($handle);

    $update = ['nama_produk', 'kategori', 'harga', 'stok_minimum', 'updated_at'];
    if ($hasTipe) {
        $update[] = 'tipe';
    }

    foreach (array_chunk(array_values($rows), 200) as $chunk) {
        Product::upsert($chunk, ['sku'], $update);
    }

    return response()->json([
        'message' => 'Berhasil import ' . count($rows) . ' produk',
        'count' => count($rows),
    ]);
}
}