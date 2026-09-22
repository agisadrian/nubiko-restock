<?php

namespace App\Imports;

use App\Models\Restock;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;

class RestocksImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    public function model(array $row)
    {
        return new Restock([
            'tanggal_kirim' => $this->parseTanggal($row['tanggal_kirim']),
            'kota_asal_gudang' => $row['kota_asal_gudang'],
            'nama_produk' => $row['nama_produk'],
            'qty' => (int) $row['qty'],
            'status' => strtolower(trim($row['status'])) === 'sudah inbound' || strtolower(trim($row['status'])) === 'sudah'
                ? 'sudah'
                : 'belum',
        ]);
    }

    private function parseTanggal($value)
    {
        if (is_numeric($value)) {
            // Excel kadang simpan tanggal sebagai angka serial
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value));
        }

        return Carbon::parse($value);
    }
}