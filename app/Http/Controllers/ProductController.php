<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

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
}