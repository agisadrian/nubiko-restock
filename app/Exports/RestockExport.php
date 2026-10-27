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

class RestockExport implements FromArray, WithStyles, ShouldAutoSize, WithTitle
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
        return 'Laporan Restock';
    }

    public function array(): array
    {
        $totalQty = $this->items->sum('qty');
        $sudah = $this->items->where('status', 'sudah')->count();
        $belum = $this->items->where('status', 'belum')->count();

        $filterDesc = [];
        if (!empty($this->filters['search'])) {
            $filterDesc[] = 'Pencarian: ' . $this->filters['search'];
        }
        if (!empty($this->filters['status'])) {
            $filterDesc[] = 'Status: ' . ($this->filters['status'] === 'sudah' ? 'Sudah Inbound' : 'Belum Inbound');
        }
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            $filterDesc[] = 'Periode: ' . ($this->filters['date_from'] ?? 'Awal') . ' s/d ' . ($this->filters['date_to'] ?? 'Sekarang');
        }
        $filterStr = count($filterDesc) > 0 ? implode(' | ', $filterDesc) : 'Semua Data';

        $rows = [
            ['NUBIKO — LAPORAN RESTOCK MASUK'],
            ['Waktu Cetak: ' . now()->translatedFormat('d F Y, H:i') . ' WIB'],
            ['Filter: ' . $filterStr],
            [],
            ['RINGKASAN EKSEKUTIF'],
            ['Total Transaksi', 'Total Kuantitas (Qty)', 'Sudah Inbound', 'Belum Inbound'],
            [(int) count($this->items), (int) $totalQty, (int) $sudah, (int) $belum],
            [],
            ['No', 'Bulan', 'Tanggal Kirim', 'Warehouse', 'Nama Produk', 'Qty', 'Status'],
        ];

        $no = 1;
        foreach ($this->items as $item) {
            $rows[] = [
                $no++,
                $item->tanggal_kirim ? Carbon::parse($item->tanggal_kirim)->translatedFormat('F') : '-',
                $item->tanggal_kirim ? Carbon::parse($item->tanggal_kirim)->format('d/m/Y') : '-',
                $item->kota_asal_gudang,
                $item->nama_produk,
                (int) $item->qty,
                $item->status === 'sudah' ? 'Sudah Inbound' : 'Belum Inbound',
            ];
        }

        $rows[] = ['', '', '', '', 'TOTAL KESELURUHAN RESTOCK', (int) $totalQty, ''];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->items) + 9;

        // Title styling
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FF4338CA');
        $sheet->getStyle('A2:A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF64748B');

        // Executive Summary
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF1E293B');
        $sheet->getStyle('A6:D6')->getFont()->setBold(true)->setSize(9)->getColor()->setARGB('FF475569');
        $sheet->getStyle('A6:D6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
        $sheet->getStyle('A7:D7')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('FF4338CA');
        $sheet->getStyle('A6:D7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');
        $sheet->getStyle('A6:D7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Data Table Header (Row 9)
        $sheet->getStyle('A9:G9')->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A9:G9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4338CA');
        $sheet->getStyle('A9:G9')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('G9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Number format and borders
        $sheet->getStyle("F10:F" . ($lastRow + 1))->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("B7:B7")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("A9:G" . ($lastRow + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');

        // Center align No, Tanggal, Status
        $sheet->getStyle("A10:A" . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C10:C" . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G10:G" . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Total row styling
        $sheet->getStyle("A" . ($lastRow + 1) . ":G" . ($lastRow + 1))->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A" . ($lastRow + 1) . ":G" . ($lastRow + 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
        $sheet->getStyle("E" . ($lastRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
