<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restock extends Model
{
    protected $fillable = [
        'tanggal_kirim',
        'kota_asal_gudang',
        'nama_produk',
        'qty',
        'status',
    ];

    protected $casts = [
        'tanggal_kirim' => 'date',
    ];
}