<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Stok Keluar</title>
    <style>
        @page {
            margin: 10mm 12mm 14mm 12mm;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2px solid #ea580c;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .brand-title {
            font-size: 16px;
            font-weight: bold;
            color: #ea580c;
            margin: 0;
        }
        .report-subtitle {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }
        .meta-info {
            text-align: right;
            font-size: 8.5px;
            color: #4b5563;
            line-height: 1.4;
        }
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 8px;
        }
        .card {
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 4px;
            padding: 6px 10px;
            text-align: center;
        }
        .card-label {
            font-size: 7.5px;
            text-transform: uppercase;
            color: #9a3412;
            font-weight: bold;
        }
        .card-value {
            font-size: 14px;
            font-weight: bold;
            color: #ea580c;
            margin-top: 2px;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
        }
        table.data-table th {
            background-color: #ea580c;
            color: #ffffff;
            font-weight: bold;
            font-size: 8.5px;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #c2410c;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.data-table th.center, table.data-table td.center {
            text-align: center;
        }
        table.data-table th.right, table.data-table td.right {
            text-align: right;
        }
        table.data-table td {
            padding: 4px 6px;
            border: 1px solid #e5e7eb;
            font-size: 8.5px;
            vertical-align: middle;
        }
        table.data-table tr:nth-child(even) td {
            background-color: #fffaf5;
        }
        .sku-text {
            font-family: monospace;
            font-size: 8.5px;
            color: #374151;
            font-weight: bold;
        }
        .footer-left {
            position: fixed;
            bottom: 0;
            left: 0;
            font-size: 7.5px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="vertical-align: top;">
                <div class="brand-title">NUBIKO — LAPORAN STOK KELUAR</div>
                <div class="report-subtitle">Riwayat transaksi pengeluaran barang & pengurangan stok</div>
            </td>
            <td class="meta-info" style="vertical-align: top;">
                <div><strong>Dicetak:</strong> {{ $printedAt }}</div>
                @if(!empty($filters['search']))
                    <div><strong>Pencarian:</strong> "{{ $filters['search'] }}"</div>
                @endif
                @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                    <div><strong>Periode:</strong> {{ $filters['date_from'] ?? 'Awal' }} s/d {{ $filters['date_to'] ?? 'Sekarang' }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="card" width="50%">
                <div class="card-label">Total Transaksi Keluar</div>
                <div class="card-value">{{ number_format(count($items), 0, ',', '.') }}</div>
            </td>
            <td class="card" width="50%">
                <div class="card-label">Total Kuantitas Keluar (Qty)</div>
                <div class="card-value">{{ number_format($totalQty, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="center" width="5%">No</th>
                <th width="14%">Tanggal Keluar</th>
                <th width="18%">SKU</th>
                <th>Nama Produk</th>
                <th class="right" width="8%">Qty</th>
                <th width="22%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->tanggal_keluar ? \Carbon\Carbon::parse($item->tanggal_keluar)->format('d/m/Y') : '-' }}</td>
                    <td><span class="sku-text">{{ $item->sku ?? '-' }}</span></td>
                    <td>{{ $item->nama_produk }}</td>
                    <td class="right"><strong>{{ number_format($item->qty, 0, ',', '.') }}</strong></td>
                    <td>{{ $item->keterangan ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center" style="padding: 16px; color: #6b7280;">Tidak ada data stok keluar yang sesuai.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer-left">Sistem Manajemen Stok Nubiko &copy; {{ date('Y') }} &bull; Dokumen Resmi Internal</div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}";
            $font = $fontMetrics->getFont("Helvetica", "normal");
            $size = 7.5;
            $color = array(0.5, 0.5, 0.5);
            $y = $pdf->get_height() - 22;
            $x = $pdf->get_width() - 95;
            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>
</body>
</html>
