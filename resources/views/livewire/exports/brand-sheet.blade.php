<table>
    <tr>
        <!-- Colspan diubah jadi 7 karena ada tambahan 1 kolom barcode -->
        <td colspan="7" style="font-size: 14px;"><strong>Laporan Detail Penjualan Brand:
                {{ $data['brand_name'] }}</strong></td>
    </tr>
    <tr>
        <td colspan="7">Outlet: {{ $outletName }}</td>
    </tr>
    <tr>
        <td colspan="7">Periode: {{ $periodRange }}</td>
    </tr>
    <tr></tr> <!-- Baris kosong sebagai jarak -->

    <tr>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">Waktu Transaksi</th>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">ID Pesanan</th>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">Barcode</th>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">Nama Produk</th>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">Harga Satuan</th>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">Terjual (Qty)</th>
        <th style="font-weight: bold; background-color: #f3f4f6; border: 1px solid #000;">Subtotal</th>
    </tr>

    @foreach($data['items'] as $item)
        <tr>
            <td style="border: 1px solid #ddd;">{{ $item->transaction->created_at->format('d/m/Y H:i') }}</td>
            <td style="border: 1px solid #ddd;">{{ $item->transaction->order_id }}</td>

            <td style="border: 1px solid #ddd; text-align: left;">
                {{ $item->variant->barcode ?? $item->product->barcode ?? '-' }}
            </td>

            <td style="border: 1px solid #ddd;">{{ $item->product_name }}</td>
            <td style="border: 1px solid #ddd;">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
            <td style="border: 1px solid #ddd; text-align: center;">{{ $item->quantity }}</td>
            <td style="border: 1px solid #ddd;">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
        </tr>
    @endforeach

    <tr></tr>

    <tr>
        <td colspan="5" style="text-align: right; font-weight: bold; border: 1px solid #000;">TOTAL PENJUALAN
            KESELURUHAN</td>
        <td style="font-weight: bold; text-align: center; border: 1px solid #000;">{{ $data['total_qty'] }}</td>
        <td style="font-weight: bold; border: 1px solid #000;">Rp
            {{ number_format($data['total_revenue'], 0, ',', '.') }}
        </td>
    </tr>
</table>