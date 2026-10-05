<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['sku', 'nama_produk', 'kategori', 'harga', 'stok_minimum'])]
class Product extends Model
{
    protected $casts = [
        'harga' => 'decimal:2',
    ];
}