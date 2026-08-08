<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        .header {
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 4px;
            color: #111827;
        }

        .meta-info {
            font-size: 13px;
            color: #4b5563;
            line-height: 1.5;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 24px;
        }

        th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
        }

        th,
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .summary-container {
            margin-bottom: 24px;
            width: 100%;
        }

        /* Mengubah lebar menjadi 17.5% agar muat 5 kotak tanpa turun baris */
        .summary-box {
            display: inline-block;
            width: 17.5%;
            padding: 10px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-right: 1%;
            vertical-align: top;
        }

        .summary-box small {
            color: #6b7280;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
            display: block;
            margin-bottom: 4px;
        }

        .summary-box strong {
            font-size: 13px;
            color: #111827;
        }

        h3 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #374151;
            border-left: 3px solid #4338ca;
            padding-left: 8px;
        }
    </style>
</head>

<body>

    <div class="header">
        <h1>Laporan Ringkasan Penjualan</h1>
        <div class="meta-info">
            <strong>Outlet:</strong> {{ $outletName }} <br>
            <strong>Periode Tren:</strong> {{ $periodRange }}
        </div>
    </div>

    <div class="summary-container">
        <div class="summary-box">
            <small>Penjualan (Harian)</small>
            <strong>Rp {{ number_format($todaySales, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-box">
            <small>Pendapatan (Semua)</small>
            <strong>Rp {{ number_format($totalRevenue, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-box">
            <small>Transaksi (Harian)</small>
            <strong>{{ number_format($todayTransactions, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-box">
            <small>Produk Terbaik</small>
            <strong>{{ $topProduct->product_name ?? 'Belum ada' }}</strong>
        </div>
        <div class="summary-box" style="margin-right: 0;">
            <small>Pajak Non-Lokal</small>
            <strong>Rp {{ number_format($taxNonLokal, 0, ',', '.') }}</strong>
        </div>
    </div>

    <h3>Rincian Metode Pembayaran</h3>
    <table>
        <tr>
            <th>Metode Pembayaran</th>
            <th class="text-right">Total Nominal</th>
        </tr>
        <tr>
            <td>Tunai (Cash)</td>
            <td class="text-right">Rp {{ number_format($paymentBreakdown['cash'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Kartu Lokal</td>
            <td class="text-right">Rp {{ number_format($paymentBreakdown['kartu_lokal'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Kartu Non-Lokal</td>
            <td class="text-right">Rp {{ number_format($paymentBreakdown['kartu_non_lokal'], 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>QRIS</td>
            <td class="text-right">Rp {{ number_format($paymentBreakdown['qris'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <h3>4 Produk Terlaris (Berdasarkan Outlet)</h3>
    <table>
        <tr>
            <th>Nama Produk</th>
            <th class="text-right">Total Terjual</th>
        </tr>
        @forelse($topSelling as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td class="text-right">{{ $item->total_sold }} unit</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center; color: #6b7280;">Belum ada data penjualan produk.</td>
            </tr>
        @endforelse
    </table>

    <h3>Transaksi Terbaru</h3>
    <table>
        <tr>
            <th>ID Pesanan</th>
            <th>Metode</th>
            <th class="text-right">Total Belanja</th>
        </tr>
        @forelse($recentTransactions as $trx)
            <tr>
                <td>{{ $trx->order_id }}</td>
                <td>{{ strtoupper($trx->payment_method) }}</td>
                <td class="text-right">Rp {{ number_format($trx->total, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3" style="text-align: center; color: #6b7280;">Belum ada data transaksi.</td>
            </tr>
        @endforelse
    </table>

</body>

</html>