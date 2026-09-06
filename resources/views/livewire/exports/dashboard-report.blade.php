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
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .summary-container {
            margin-bottom: 24px;
            width: 100%;
        }

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
            border-left: 3px solid #000000;
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
            <small>Total Pendapatan</small>
            <strong>Rp {{ number_format($totalRevenue, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-box">
            <small>Total Transaksi</small>
            <strong>{{ number_format($totalTransactions, 0, ',', '.') }}</strong>
        </div>
        <div class="summary-box">
            <small>Item Terjual</small>
            <strong>{{ number_format($totalItemsSold, 0, ',', '.') }}</strong>
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
            <th>Nama Produk & Brand</th>
            <th class="text-right">Total Terjual</th>
        </tr>
        @forelse($topSelling as $item)
            <tr>
                <td>
                    {{ $item->product_name }}
                    <!-- Menampilkan nama brand jika ada -->
                    @if($item->product?->brand)
                        <br><small style="color: #6b7280;">Brand: {{ $item->product->brand->name }}</small>
                    @endif
                </td>
                <td class="text-right">{{ $item->total_sold }} unit</td>
            </tr>
        @empty
            <tr>
                <td colspan="2" style="text-align: center; color: #6b7280;">Belum ada data penjualan produk.</td>
            </tr>
        @endforelse
    </table>

    <h3>Detail Seluruh Transaksi</h3>
    <table>
        <tr>
            <th>Waktu</th>
            <th>ID Pesanan</th>
            <th>Barcode</th>
            <th>Rincian Item</th>
            <th>Brand</th>
            <th>Metode</th>
            <th class="text-right">Total</th>
        </tr>
        @forelse($allTransactions as $trx)
            <tr>
                <td>{{ $trx->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $trx->order_id }}</td>

                <td>
                    <ul style="margin: 0; padding-left: 0; list-style-type: none;">
                        @foreach($trx->items as $item)
                            <li style="margin-bottom: 4px; font-weight: bold;">
                                {{ $item->variant->barcode ?? $item->product->barcode ?? '-' }}
                            </li>
                        @endforeach
                    </ul>
                </td>

                <td>
                    <ul style="margin: 0; padding-left: 15px;">
                        @foreach($trx->items as $item)
                            <li style="margin-bottom: 4px;">
                                {{ $item->product_name }}
                                ({{ $item->quantity }}x) - Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                            </li>
                        @endforeach
                    </ul>
                </td>

                <!-- Isi Kolom rand -->
                <td>
                    <ul style="margin: 0; padding-left: 0; list-style-type: none;">
                        @foreach($trx->items as $item)
                            <li style="margin-bottom: 4px; color: #4b5563;">
                                {{ $item->product?->brand?->name ?? '-' }}
                            </li>
                        @endforeach
                    </ul>
                </td>

                <td>{{ strtoupper(str_replace('_', ' ', $trx->payment_method)) }}</td>
                <td class="text-right" style="font-weight: bold;">Rp {{ number_format($trx->total, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align: center; color: #6b7280;">Belum ada data transaksi pada periode ini.</td>
            </tr>
        @endforelse
    </table>

</body>

</html>