<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class StockOutExport implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    protected $items;
    protected $filters;

    public function __construct($items, array $filters = [])
    {
        $this->items = $items;
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Laporan Stok Keluar';
    }

    public function array(): array
    {
        $totalQty = $this->items->sum('qty');

        $filterDesc = [];
        if (!empty($this->filters['search'])) {
            $filterDesc[] = 'Pencarian: ' . $this->filters['search'];
        }
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $filterDesc[] = 'Periode: ' . ($this->filters['date_from'] ?? 'Awal') . ' s/d ' . ($this->filters['date_to'] ?? 'Sekarang');
        }
        $filterStr = count($filterDesc) > 0 ? implode(' | ', $filterDesc) : 'Semua Data';

        $rows = [
            ['NUBIKO — LAPORAN STOK KELUAR'],
            ['Waktu Cetak: ' . now()->translatedFormat('d F Y, H:i') . ' WIB'],
            ['Filter: ' . $filterStr],
            [],
            ['RINGKASAN EKSEKUTIF'],
            ['Total Transaksi Keluar', 'Total Kuantitas Keluar (Qty)'],
            [(int) count($this->items), (int) $totalQty],
            [],
            ['No', 'Tanggal Keluar', 'SKU', 'Nama Produk', 'Qty', 'Keterangan'],
        ];

        $no = 1;
        foreach ($this->items as $item) {
            $rows[] = [
                $no++,
                $item->tanggal_keluar ? Carbon::parse($item->tanggal_keluar)->format('d/m/Y') : '-',
                $item->sku ?? '-',
                $item->nama_produk,
                (int) $item->qty,
                $item->keterangan ?: '-',
            ];
        }

        $rows[] = ['', '', '', 'TOTAL STOK KELUAR', (int) $totalQty, ''];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->items) + 9;

        // Title styling
        $sheet->mergeCells('A1:F1');
        $sheet->mergeCells('A2:F2');
        $sheet->mergeCells('A3:F3');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FFEA580C');
        $sheet->getStyle('A2:A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF64748B');

        // Executive Summary
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF1E293B');
        $sheet->getStyle('A6:B6')->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FF9A3412');
        $sheet->getStyle('A6:B6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEDD5');
        $sheet->getStyle('A7:B7')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FFEA580C');
        $sheet->getStyle('A6:B7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFEA580C');
        $sheet->getStyle('A6:B7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data Table Header (Row 9)
        $sheet->getStyle('A9:F9')->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A9:F9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEA580C');
        $sheet->getStyle('A9:F9')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Number format and borders
        $sheet->getStyle("E10:E" . ($lastRow + 1))->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("B7:B7")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A9:F" . ($lastRow + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

        // Center align No, Tanggal, SKU
        $sheet->getStyle("A10:A" . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B10:B" . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Total row styling
        $sheet->getStyle("A" . ($lastRow + 1) . ":F" . ($lastRow + 1))->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A" . ($lastRow + 1) . ":F" . ($lastRow + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEDD5');
        $sheet->getStyle("D" . ($lastRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
