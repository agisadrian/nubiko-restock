<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOut extends Model
{
    protected $fillable = ['tanggal_keluar', 'nama_produk', 'qty', 'keterangan'];

    protected $casts = [
        'tanggal_keluar' => 'date',
    ];
}