<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Restock Masuk</title>
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
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .brand-title {
            font-size: 16px;
            font-weight: bold;
            color: #4338ca;
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
            border-spacing: 6px 0;
            margin-bottom: 8px;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5px 8px;
            text-align: center;
        }
        .card-label {
            font-size: 7.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .card-value {
            font-size: 13px;
            font-weight: bold;
            margin-top: 2px;
        }
        .indigo { color: #4338ca; }
        .emerald { color: #059669; }
        .amber { color: #d97706; }

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
            background-color: #4338ca;
            color: #ffffff;
            font-weight: bold;
            font-size: 8.5px;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #3730a3;
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
            background-color: #f8fafc;
        }
        .status-sudah {
            color: #047857;
            font-weight: bold;
        }
        .status-belum {
            color: #b45309;
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
                <div class="brand-title">NUBIKO — LAPORAN RESTOCK MASUK</div>
                <div class="report-subtitle">Data transaksi penerimaan dan pengiriman barang restock</div>
            </td>
            <td class="meta-info" style="vertical-align: top;">
                <div><strong>Dicetak:</strong> {{ $printedAt }}</div>
                @if(!empty($filters['search']))
                    <div><strong>Pencarian:</strong> "{{ $filters['search'] }}"</div>
                @endif
                @if(!empty($filters['status']))
                    <div><strong>Status:</strong> {{ $filters['status'] === 'sudah' ? 'Sudah Inbound' : 'Belum Inbound' }}</div>
                @endif
                @if(!empty($filters['date_from']) || !empty($filters['date_to']))
                    <div><strong>Periode:</strong> {{ $filters['date_from'] ?? 'Awal' }} s/d {{ $filters['date_to'] ?? 'Sekarang' }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="summary-table">
        <tr>
            <td class="card" width="25%">
                <div class="card-label">Total Transaksi</div>
                <div class="card-value indigo">{{ number_format(count($items), 0, ',', '.') }}</div>
            </td>
            <td class="card" width="25%">
                <div class="card-label">Total Qty Restock</div>
                <div class="card-value indigo">{{ number_format($totalQty, 0, ',', '.') }}</div>
            </td>
            <td class="card" width="25%">
                <div class="card-label">Sudah Inbound</div>
                <div class="card-value emerald">{{ number_format($totalSudah, 0, ',', '.') }}</div>
            </td>
            <td class="card" width="25%">
                <div class="card-label">Belum Inbound</div>
                <div class="card-value amber">{{ number_format($totalBelum, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="center" width="4%">No</th>
                <th width="10%">Bulan</th>
                <th width="11%">Tanggal Kirim</th>
                <th width="18%">Warehouse</th>
                <th>Nama Produk</th>
                <th class="right" width="8%">Qty</th>
                <th class="center" width="13%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->tanggal_kirim ? \Carbon\Carbon::parse($item->tanggal_kirim)->translatedFormat('F') : '-' }}</td>
                    <td>{{ $item->tanggal_kirim ? \Carbon\Carbon::parse($item->tanggal_kirim)->format('d/m/Y') : '-' }}</td>
                    <td>{{ $item->kota_asal_gudang }}</td>
                    <td>{{ $item->nama_produk }}</td>
                    <td class="right"><strong>{{ number_format($item->qty, 0, ',', '.') }}</strong></td>
                    <td class="center">
                        @if($item->status === 'sudah')
                            <span class="status-sudah">&#10003; Sudah Inbound</span>
                        @else
                            <span class="status-belum">&bull; Belum Inbound</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center" style="padding: 16px; color: #6b7280;">Tidak ada data restock yang sesuai.</td>
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
