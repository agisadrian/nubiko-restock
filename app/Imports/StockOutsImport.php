<?php

namespace App\Imports;

use App\Models\StockOut;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StockOutsImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    public function model(array $row)
    {
        return new StockOut([
            'tanggal_keluar' => $this->parseTanggal($row['tanggal_keluar']),
            'nama_produk' => $row['nama_produk'],
            'qty' => (int) $row['qty'],
            'keterangan' => $row['keterangan'] ?? null,
        ]);
    }

    private function parseTanggal($value)
    {
        if (is_numeric($value)) {
            return Carbon::instance(Date::excelToDateTimeObject($value));
        }
        return Carbon::parse($value);
    }
}